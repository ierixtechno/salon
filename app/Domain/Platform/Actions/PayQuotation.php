<?php

namespace App\Domain\Platform\Actions;

use App\Domain\Core\Actions\SendNotification;
use App\Domain\Platform\Actions\Concerns\GeneratesPlatformSequenceNumbers;
use App\Domain\Platform\Models\PlatformInvoice;
use App\Domain\Platform\Models\Quotation;
use App\Domain\Platform\Models\Tenant;
use App\Domain\Platform\Models\TenantSubscription;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * The core of the billing flow: turns a paid Quotation into an immutable
 * PlatformInvoice, and — per the confirmed decision — auto-syncs the
 * tenant's enabled modules to exactly the paid plan's modules via the
 * existing UpdateTenantModules action. That action *replaces* the tenant's
 * module set rather than adding to it, so paying a smaller/different plan
 * can disable modules the tenant had before — this is intentional
 * (billing and module access must stay consistent), not a bug.
 */
class PayQuotation
{
    use GeneratesPlatformSequenceNumbers;

    public function __construct(private readonly UpdateTenantModules $updateTenantModules) {}

    public function execute(Quotation $quotation, string $paymentMethod, ?string $paymentReference): PlatformInvoice
    {
        // Idempotency guard — a double-click or a retried confirm request
        // must not double-invoice/double-sync (CLAUDE.md §24/§37).
        abort_if($quotation->status !== 'pending', 409, 'This quotation is no longer payable.');

        $invoice = DB::transaction(function () use ($quotation, $paymentMethod, $paymentReference) {
            $invoiceNumber = $this->nextPlatformNumber('invoice');

            $invoice = PlatformInvoice::create([
                'tenant_id' => $quotation->tenant_id,
                'quotation_id' => $quotation->id,
                'subscription_plan_id' => $quotation->subscription_plan_id,
                'invoice_number' => $invoiceNumber,
                'amount' => $quotation->amount,
                'payment_method' => $paymentMethod,
                'payment_reference' => $paymentReference,
                'paid_at' => now(),
            ]);

            $quotation->update(['status' => 'paid', 'paid_at' => now()]);

            $plan = $quotation->plan;
            $this->updateTenantModules->execute($quotation->tenant, $plan->modules->pluck('code')->all());

            // First-ever payment activates a still-pending tenant (see
            // OnboardTenant — signups no longer get a free trial). Renewals
            // never touch Tenant.status again after this, only
            // TenantSubscription below.
            if ($quotation->tenant->status === 'pending_payment') {
                $quotation->tenant->update(['status' => 'active']);
            }

            TenantSubscription::updateOrCreate(
                ['tenant_id' => $quotation->tenant_id, 'subscription_plan_id' => $plan->id],
                [
                    'status' => 'active',
                    'starts_at' => now(),
                    'ends_at' => match ($plan->billing_interval) {
                        'yearly' => now()->addYear(),
                        default => now()->addMonth(),
                    },
                    'trial_ends_at' => null,
                ],
            );

            $this->notifyTenant($quotation, $invoiceNumber, $invoice->id);

            return $invoice;
        });

        // Outside the transaction so the cache is only cleared once the
        // write has actually committed — busts EnforceSubscriptionAccess's
        // cached state so this request's *next* page load is unlocked
        // immediately, no re-login required.
        Tenant::forgetSubscriptionCache($quotation->tenant_id);

        return $invoice;
    }

    private function notifyTenant(Quotation $quotation, string $invoiceNumber, int $invoiceId): void
    {
        $tenant = $quotation->tenant;
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

        $tenant->users()
            ->get()
            ->filter(fn ($user) => $user->can('tenant.billing.manage'))
            ->each(function ($user) use ($tenant, $invoiceNumber, $invoiceId) {
                app(SendNotification::class)->execute(
                    tenantId: $tenant->id,
                    channel: 'in_app',
                    recipientType: 'user',
                    recipientId: $user->id,
                    toAddress: null,
                    subject: 'Payment received',
                    body: "Your payment was received. Invoice {$invoiceNumber} is available.",
                    referenceType: 'PlatformInvoice',
                    referenceId: $invoiceId,
                );
            });
    }
}
