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
    public function execute(TenantSubscription $currentSubscription, SubscriptionPlan $newPlan): string
    {
        $priceDifference = (float) $newPlan->price - (float) $currentSubscription->plan->price;

        $cycleStart = $currentSubscription->starts_at->copy()->startOfDay();
        $cycleEnd = $currentSubscription->ends_at->copy()->startOfDay();
        $today = now()->startOfDay();

        $cycleDays = max(1, $cycleStart->diffInDays($cycleEnd));
        $remainingDays = max(0, $today->diffInDays($cycleEnd));

        $prorated = round(($priceDifference / $cycleDays) * $remainingDays, 2);

        return number_format(max(0, $prorated), 2, '.', '');
    }
}
