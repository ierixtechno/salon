<?php

namespace App\Domain\Platform\Actions;

use App\Domain\Platform\Models\Quotation;
use App\Domain\Platform\Models\Tenant;

/**
 * A tenant buying more branches mid-cycle on their current plan. Charged
 * pro rata for the days left in the current billing cycle (same idea as a
 * plan upgrade — the renewal date does not move), then renewed at the full
 * price for the new branch count.
 */
class RequestExtraBranches
{
    public function __construct(
        private readonly CalculateProration $calculateProration,
        private readonly CreateQuotation $createQuotation,
    ) {}

    public function execute(Tenant $tenant, int $additional): Quotation
    {
        abort_if($additional < 1, 422, 'Choose at least one branch to add.');

        abort_if(
            Quotation::where('tenant_id', $tenant->id)->where('status', 'pending')->exists(),
            409,
            'You already have a pending quotation — resolve it before requesting another.',
        );

        $subscription = $tenant->currentSubscription();

        abort_unless(
            $subscription && $subscription->ends_at && now()->lte($subscription->ends_at),
            422,
            'You need an active subscription to add branches.',
        );

        $plan = $subscription->plan;

        abort_unless($plan->sellsExtraBranches(), 422, 'Additional branches are not available on your plan. Upgrade to a plan that offers them.');

        $current = $subscription->currentBranchCount();
        $newTotal = $current + $additional;

        abort_if($newTotal > $plan->maxBranches(), 422, "Your plan allows at most {$plan->maxBranches()} branches.");

        $amount = $this->calculateProration->prorate($additional * (float) $plan->additional_branch_price, $subscription);

        abort_if((float) $amount <= 0, 422, 'Your subscription renews very soon — add branches after renewing.');

        return $this->createQuotation->execute(
            tenant: $tenant,
            plan: $plan,
            createdBy: null,
            amountOverride: $amount,
            notes: "Prorated: {$additional} additional ".str('branch')->plural($additional)." (total {$newTotal}).",
            isUpgrade: true,
            branchCount: $newTotal,
        );
    }
}
