<?php

namespace App\Domain\Platform\Actions;

use App\Domain\Platform\Actions\Concerns\GeneratesPlatformSequenceNumbers;
use App\Domain\Platform\Models\PlatformInvoice;
use App\Domain\Platform\Models\Quotation;
use App\Domain\Platform\Models\SubscriptionPlan;
use App\Domain\Platform\Models\Tenant;
use App\Domain\Platform\Models\TenantSubscription;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The core of the billing flow: turns a paid Quotation into an immutable
 * PlatformInvoice (and removes the quotation — it has served its purpose), and — per the confirmed decision — auto-syncs the
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
            // The quotation is deleted once paid, so the unique quotation_id on the
            // invoice no longer guards against a double payment (webhook racing the
            // browser confirm). Lock the row and re-check under the lock instead.
            $locked = Quotation::whereKey($quotation->id)->lockForUpdate()->first();
            abort_if(! $locked || $locked->status !== 'pending', 409, 'This quotation is no longer payable.');

            $invoiceNumber = $this->nextPlatformNumber('invoice');

            $invoice = PlatformInvoice::create([
                'tenant_id' => $quotation->tenant_id,
                'quotation_number' => $quotation->quotation_number,
                'subscription_plan_id' => $quotation->subscription_plan_id,
                'invoice_number' => $invoiceNumber,
                // The invoice records what was actually collected —
                // total_amount (tax-inclusive), not the pre-tax `amount`.
                // subtotal/cgst/sgst/rate are copied verbatim so the
                // invoice is a self-contained historical record.
                'amount' => $quotation->total_amount,
                'subtotal' => $quotation->amount,
                'cgst_amount' => $quotation->cgst_amount,
                'sgst_amount' => $quotation->sgst_amount,
                'igst_amount' => $quotation->igst_amount,
                'gst_rate_percent' => $quotation->gst_rate_percent,
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
            $firstActivation = $quotation->tenant->status === 'pending_payment';
            if ($firstActivation) {
                $quotation->tenant->update(['status' => 'active']);
            }

            $endsAt = $this->resolveEndsAt($quotation, $plan);

            // Buying extra branches on the SAME plan mid-cycle must not restart the
            // cycle (proration is measured from its start).
            $existing = TenantSubscription::where('tenant_id', $quotation->tenant_id)->where('subscription_plan_id', $plan->id)->first();
            $keepCycle = $quotation->is_upgrade && $existing !== null;

            TenantSubscription::updateOrCreate(
                ['tenant_id' => $quotation->tenant_id, 'subscription_plan_id' => $plan->id],
                [
                    'branch_count' => $plan->clampBranches($quotation->branch_count),
                    'status' => 'active',
                    'starts_at' => $keepCycle ? $existing->starts_at : now(),
                    'ends_at' => $endsAt,
                    'trial_ends_at' => null,
                ],
            );

            app(NotifyTenantBillingContacts::class)->paymentReceived($quotation, $invoice, $endsAt, $firstActivation);

            // A paid quotation is not kept: the invoice (which carries its
            // number) is the record that remains.
            $quotation->delete();

            return $invoice;
        });

        // Outside the transaction so the cache is only cleared once the
        // write has actually committed — busts EnforceSubscriptionAccess's
        // cached state so this request's *next* page load is unlocked
        // immediately, no re-login required.
        Tenant::forgetSubscriptionCache($quotation->tenant_id);

        return $invoice;
    }

    /**
     * A normal payment starts a fresh cycle from today. A prorated upgrade
     * (RequestPlanUpgrade) does the opposite on purpose — the tenant is
     * finishing out their *existing* cycle on the new plan, so the renewal
     * date must not move. The old plan's TenantSubscription row is marked
     * 'cancelled' (superseded mid-cycle, not naturally expired) so
     * currentSubscription() cleanly picks up the new one.
     */
    private function resolveEndsAt(Quotation $quotation, SubscriptionPlan $plan): Carbon
    {
        $freshCycleEnd = fn () => match ($plan->billing_interval) {
            'yearly' => now()->addYear(),
            default => now()->addMonth(),
        };

        if (! $quotation->is_upgrade) {
            return $freshCycleEnd();
        }

        $previousSubscription = $quotation->tenant->currentSubscription();

        if ($previousSubscription && $previousSubscription->subscription_plan_id !== $plan->id) {
            $previousSubscription->update(['status' => 'cancelled']);
        }

        return $previousSubscription?->ends_at ?? $freshCycleEnd();
    }
}
