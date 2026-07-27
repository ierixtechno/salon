<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Models\MembershipPlan;

/**
 * Empty array means "applies to all" for that dimension (MembershipPlan::
 * appliesToServiceAtBranch's convention) — sync([]) correctly clears the
 * pivot back to that state.
 */
class UpdateMembershipPlanApplicability
{
    public function execute(MembershipPlan $plan, array $moduleIds, array $branchIds, array $serviceIds): void
    {
        $plan->modules()->sync($moduleIds);
        $plan->branches()->sync($branchIds);
        $plan->services()->sync($serviceIds);
    }
}
