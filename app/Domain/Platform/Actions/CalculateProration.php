<?php

namespace App\Domain\Platform\Actions;

use App\Domain\Platform\Models\SubscriptionPlan;
use App\Domain\Platform\Models\TenantSubscription;

/**
 * The prorated cost of upgrading mid-cycle: the plan-to-plan price
 * difference, charged only for the days remaining in the tenant's current
 * billing cycle (RequestPlanUpgrade preserves ends_at rather than
 * extending it, so this is genuinely "pay for the upgrade for the time
 * you have left," not a fresh full-price charge).
 *
 * Whole-day diffs via startOfDay() (same idiom as
 * ResolveSubscriptionAccessState) so the amount is stable within a
 * calendar day rather than drifting with time-of-day.
 */
class CalculateProration
{
    public function execute(TenantSubscription $currentSubscription, SubscriptionPlan $newPlan, ?int $newBranchCount = null): string
    {
        $currentCount = $currentSubscription->currentBranchCount();
        $newCount = $newPlan->clampBranches($newBranchCount ?? $currentCount);

        $priceDifference = $newPlan->priceForBranches($newCount) - $currentSubscription->plan->priceForBranches($currentCount);

        return $this->prorate($priceDifference, $currentSubscription);
    }

    /** Charge `$priceDifference` (a per-cycle amount) only for the days left in the current cycle. */
    public function prorate(float $priceDifference, TenantSubscription $currentSubscription): string
    {
        $cycleStart = $currentSubscription->starts_at->copy()->startOfDay();
        $cycleEnd = $currentSubscription->ends_at->copy()->startOfDay();
        $today = now()->startOfDay();

        $cycleDays = max(1, $cycleStart->diffInDays($cycleEnd));
        $remainingDays = max(0, $today->diffInDays($cycleEnd));

        $prorated = round(($priceDifference / $cycleDays) * $remainingDays, 2);

        return number_format(max(0, $prorated), 2, '.', '');
    }
}
