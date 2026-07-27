<?php

namespace App\Policies;

use App\Domain\Core\Models\MembershipPlan;
use App\Models\User;

class MembershipPlanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('memberships.view');
    }

    public function view(User $user, MembershipPlan $plan): bool
    {
        return $user->can('memberships.view') && $user->tenant_id === $plan->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->can('memberships.create');
    }

    public function update(User $user, MembershipPlan $plan): bool
    {
        return $user->can('memberships.update') && $user->tenant_id === $plan->tenant_id;
    }

    public function delete(User $user, MembershipPlan $plan): bool
    {
        return $user->can('memberships.delete') && $user->tenant_id === $plan->tenant_id;
    }
}
