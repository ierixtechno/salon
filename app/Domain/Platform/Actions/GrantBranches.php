<?php

namespace App\Domain\Platform\Actions;

use App\Domain\Platform\Models\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * Super Admin adds branches to a tenant's subscription WITHOUT a charge
 * (complimentary, or already paid for outside the app). The tenant's branch
 * limit — and, through it, its user limit — rises immediately. The plan's
 * maximum still applies; to go beyond it, change the plan.
 *
 * To charge for the branches instead, use RequestExtraBranches (a pro-rata
 * quotation).
 */
class GrantBranches
{
    /** @return int the tenant's new total number of branches */
    public function execute(Tenant $tenant, int $additional): int
    {
        abort_if($additional < 1, 422, 'Choose at least one branch to add.');

        return DB::transaction(function () use ($tenant, $additional) {
            $subscription = $tenant->subscriptions()
                ->whereIn('status', ['trialing', 'active', 'expired'])
                ->latest('starts_at')
                ->lockForUpdate()
                ->first();

            abort_unless($subscription, 422, 'This tenant has no subscription yet — they must pay their quotation first.');

            $plan = $subscription->plan;
            $newTotal = $subscription->currentBranchCount() + $additional;

            abort_if(
                $newTotal > $plan->maxBranches(),
                422,
                $plan->sellsExtraBranches()
                    ? "This plan allows at most {$plan->maxBranches()} branches."
                    : 'This plan does not offer additional branches. Set an "Each additional branch" price on the plan first, or move the tenant to another plan.',
            );

            $subscription->update(['branch_count' => $newTotal]);
            Tenant::forgetSubscriptionCache($tenant->id);

            return $newTotal;
        });
    }
}
