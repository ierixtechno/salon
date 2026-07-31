<?php

namespace App\Domain\Platform\Actions;

use App\Domain\Core\Actions\SendNotification;
use App\Domain\Platform\Actions\Concerns\GeneratesPlatformSequenceNumbers;
use App\Domain\Platform\Models\PlatformAdmin;
use App\Domain\Platform\Models\Quotation;
use App\Domain\Platform\Models\SubscriptionPlan;
use App\Domain\Platform\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Bills a specific tenant for a subscription plan. Amount defaults to the
 * plan's price but the Super Admin may override it (e.g. a negotiated
 * discount) — never a freeform line-item builder (confirmed decision).
 */
class CreateQuotation
{
    use GeneratesPlatformSequenceNumbers;

    public function execute(
        Tenant $tenant,
        SubscriptionPlan $plan,
        ?PlatformAdmin $createdBy,
        ?string $amountOverride = null,
        ?string $notes = null,
        bool $isUpgrade = false,
    ): Quotation {
        abort_unless($plan->is_active, 422, 'Cannot quote an inactive plan.');

        return DB::transaction(function () use ($tenant, $plan, $createdBy, $amountOverride, $notes, $isUpgrade) {
            $quotationNumber = $this->nextPlatformNumber('quotation');

            $quotation = Quotation::create([
                'tenant_id' => $tenant->id,
                'subscription_plan_id' => $plan->id,
                'platform_admin_id' => $createdBy?->id,
                'quotation_number' => $quotationNumber,
                'amount' => $amountOverride ?? $plan->price,
                'notes' => $notes,
                'status' => 'pending',
                'is_upgrade' => $isUpgrade,
            ]);

            $this->notifyTenant($tenant, "A new quotation ({$quotationNumber}) is awaiting your review.", $quotation->id);

            return $quotation;
        });
    }

    /**
     * Notifies every tenant user with tenant.billing.manage (Owner-only
     * today) via the existing in-app notification pipeline. tenant()->users()
     * already bypasses TenantScope (see Tenant model), and SendNotification
     * takes an explicit tenantId — safe to call from this platform-guard
     * context.
     */
    private function notifyTenant(Tenant $tenant, string $body, int $referenceId): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

        $tenant->users()
            ->get()
            ->filter(fn ($user) => $user->can('tenant.billing.manage'))
            ->each(function ($user) use ($tenant, $body, $referenceId) {
                app(SendNotification::class)->execute(
                    tenantId: $tenant->id,
                    channel: 'in_app',
                    recipientType: 'user',
                    recipientId: $user->id,
                    toAddress: null,
                    subject: 'Billing update',
                    body: $body,
                    referenceType: 'Quotation',
                    referenceId: $referenceId,
                );
            });
    }
}
