<?php

namespace App\Policies;

use App\Domain\Core\Models\Branch;
use App\Models\User;

/**
 * TenantScope already prevents route-model binding from resolving a
 * cross-tenant branch (it 404s before the policy even runs), but the
 * explicit tenant_id check here is deliberate defense-in-depth per
 * CLAUDE.md §32 (IDOR Prevention) — never rely on a single layer.
 */
class BranchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('branches.view');
    }

    public function view(User $user, Branch $branch): bool
    {
        return $user->can('branches.view') && $user->tenant_id === $branch->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->can('branches.create');
    }

    public function update(User $user, Branch $branch): bool
    {
        return $user->can('branches.update') && $user->tenant_id === $branch->tenant_id;
    }

    public function delete(User $user, Branch $branch): bool
    {
        return $user->can('branches.delete') && $user->tenant_id === $branch->tenant_id;
    }
}
