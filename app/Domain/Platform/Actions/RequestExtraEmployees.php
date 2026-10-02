<?php

namespace App\Domain\Platform\Actions;

use App\Domain\Platform\Models\Quotation;
use App\Domain\Platform\Models\Tenant;

/**
 * A tenant buying more employee slots directly, independent of branches —
 * e.g. one branch running 15 staff instead of the included 5, without being
 * forced to buy (and manage) a branch it doesn't need. Mirrors
 * RequestExtraBranches: charged pro rata for the days left in the current
 * billing cycle, then renewed at the full price going forward
 * (ProcessSubscriptionRenewals bills for it explicitly — see
 * CreateQuotation's default price formula).
 */
class RequestExtraEmployees
{
    public function __construct(
        private readonly CalculateProration $calculateProration,
        private readonly CreateQuotation $createQuotation,
    ) {}

    public function execute(Tenant $tenant, int $additional): Quotation
    {
        abort_if($additional < 1, 422, 'Choose at least one employee slot to add.');

        abort_if(
            Quotation::where('tenant_id', $tenant->id)->where('status', 'pending')->exists(),
            409,
            'You already have a pending quotation — resolve it before requesting another.',
        );

        $subscription = $tenant->currentSubscription();

        abort_unless(
            $subscription && $subscription->ends_at && now()->lte($subscription->ends_at),
            422,
            'You need an active subscription to add employees.',
        );

        $plan = $subscription->plan;

        abort_unless($plan->sellsExtraEmployees(), 422, 'Additional employees are not available on your plan. Upgrade to a plan that offers them.');
        abort_if($plan->users_included === null, 422, 'Your plan already has no employee limit — there is nothing to add.');

        $currentExtra = $subscription->currentExtraUserCount();
        $newExtraTotal = $currentExtra + $additional;

        if ($plan->max_users !== null) {
            // Uncapped total, so we can tell the tenant the real reason (the plan
            // maximum) rather than silently charging for slots the cap would
            // throw away (SubscriptionPlan::totalUserLimit caps at read time).
            $uncappedTotal = $plan->userLimitFor($subscription->currentBranchCount()) + $newExtraTotal;
            abort_if($uncappedTotal > $plan->max_users, 422, "Your plan allows at most {$plan->max_users} employees in total.");
        }

        $amount = $this->calculateProration->prorate($additional * (float) $plan->additional_employee_price, $subscription);

        abort_if((float) $amount <= 0, 422, 'Your subscription renews very soon — add employees after renewing.');

        return $this->createQuotation->execute(
            tenant: $tenant,
            plan: $plan,
            createdBy: null,
            amountOverride: $amount,
            notes: "Prorated: {$additional} additional employee slot(s) (total purchased: {$newExtraTotal}).",
            isUpgrade: true,
            // Branches are unaffected by this purchase — keep them as they are.
            branchCount: $subscription->currentBranchCount(),
            extraUserCount: $newExtraTotal,
        );
    }
}
