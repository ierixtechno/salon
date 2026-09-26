<?php

namespace App\Domain\Platform\Actions;

use App\Domain\Platform\Models\Quotation;
use App\Domain\Platform\Models\SubscriptionPlan;
use App\Domain\Platform\Models\Tenant;

/**
 * The tenant-initiated counterpart to CreateQuotation — a tenant can only
 * ever request an upgrade (a strictly higher-priced plan in the same
 * billing_interval as their current one), never a downgrade or a lateral/
 * cross-interval move. See CalculateProration for why cross-interval
 * changes are out of scope: with ends_at preserved rather than extended,
 * there's no sound way to prorate a monthly plan's remaining days against
 * a yearly plan's price.
 */
class RequestPlanUpgrade
{
    public function __construct(
        private readonly CalculateProration $calculateProration,
        private readonly CreateQuotation $createQuotation,
    ) {}

    public function execute(Tenant $tenant, SubscriptionPlan $newPlan): Quotation
    {
        abort_if(
            Quotation::where('tenant_id', $tenant->id)->where('status', 'pending')->exists(),
            409,
            'You already have a pending quotation — resolve it before requesting another.',
        );

        $currentSubscription = $tenant->currentSubscription();

        abort_unless(
            $currentSubscription && $currentSubscription->ends_at && now()->lte($currentSubscription->ends_at),
            422,
            'You need an active subscription to upgrade.',
        );

        abort_unless($newPlan->is_active, 422, 'This plan is not available.');

        $currentPlan = $currentSubscription->plan;

        abort_unless(
            $newPlan->billing_interval === $currentPlan->billing_interval,
            422,
            'You can only switch to a plan with the same billing cycle.',
        );

        abort_unless(
            (float) $newPlan->price > (float) $currentPlan->price,
            422,
            'You can only upgrade to a higher-priced plan.',
        );

        // Keep the branches they already have (within what the new plan allows).
        $branchCount = $newPlan->clampBranches($currentSubscription->currentBranchCount());

        $proratedAmount = $this->calculateProration->execute($currentSubscription, $newPlan, $branchCount);

        return $this->createQuotation->execute(
            tenant: $tenant,
            plan: $newPlan,
            createdBy: null,
            amountOverride: $proratedAmount,
            notes: "Prorated upgrade from {$currentPlan->name} to {$newPlan->name}.",
            isUpgrade: true,
            branchCount: $branchCount,
        );
    }
}
