<?php

namespace App\Domain\Platform\Support;

use App\Domain\Platform\Models\Tenant;
use Illuminate\Support\Facades\Cache;

/**
 * Access control (EnforceSubscriptionAccess) always recomputes this live
 * from TenantSubscription.ends_at — never from the cosmetic 'expired'
 * status ProcessSubscriptionRenewals writes — so a missed/late cron run
 * can't cause an access-control bug. Cached briefly (busted by PayQuotation
 * the instant a payment lands) since it's checked on effectively every
 * tenant request, mirroring Tenant::hasModuleEnabled()'s pattern. Only a
 * plain array is ever stored in cache — see SubscriptionAccessState's
 * docblock for why.
 */
class ResolveSubscriptionAccessState
{
    public const GRACE_DAYS = 7;

    public function execute(Tenant $tenant): SubscriptionAccessState
    {
        $data = Cache::remember(
            Tenant::subscriptionCacheKey($tenant->id),
            300,
            fn () => $this->resolve($tenant)->toArray(),
        );

        return SubscriptionAccessState::fromArray($data);
    }

    private function resolve(Tenant $tenant): SubscriptionAccessState
    {
        $subscription = $tenant->currentSubscription();

        if (! $subscription || ! $subscription->ends_at) {
            return new SubscriptionAccessState(level: 'pending');
        }

        $planName = $subscription->plan?->name;
        $billingInterval = $subscription->plan?->billing_interval;
        $endsAt = $subscription->ends_at;
        $today = now()->startOfDay();
        $endsAtDay = $endsAt->copy()->startOfDay();

        if (now()->lte($endsAt)) {
            $daysUntilEnd = (int) $today->diffInDays($endsAtDay);
            $threshold = $billingInterval === 'yearly' ? 15 : 5;

            return new SubscriptionAccessState(
                level: 'active',
                planName: $planName,
                reminderDaysLeft: $daysUntilEnd <= $threshold ? $daysUntilEnd : null,
            );
        }

        $daysSinceExpiry = (int) $endsAtDay->diffInDays($today);

        if ($daysSinceExpiry <= self::GRACE_DAYS) {
            return new SubscriptionAccessState(
                level: 'grace',
                planName: $planName,
                graceDaysLeft: self::GRACE_DAYS - $daysSinceExpiry,
                daysSinceExpiry: $daysSinceExpiry,
            );
        }

        return new SubscriptionAccessState(
            level: 'blocked',
            planName: $planName,
            daysSinceExpiry: $daysSinceExpiry,
        );
    }
}
