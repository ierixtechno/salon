<?php

namespace App\Policies;

use App\Domain\Core\Models\Resource as BranchResource;
use App\Models\User;

class ResourcePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('resources.view');
    }

    public function view(User $user, BranchResource $resource): bool
    {
        return $user->can('resources.view')
            && $user->tenant_id === $resource->tenant_id
            && $user->canAccessBranch($resource->branch);
    }

    public function create(User $user): bool
    {
        return $user->can('resources.create');
    }

    public function update(User $user, BranchResource $resource): bool
    {
        return $user->can('resources.update')
            && $user->tenant_id === $resource->tenant_id
            && $user->canAccessBranch($resource->branch);
    }

    public function delete(User $user, BranchResource $resource): bool
    {
        return $user->can('resources.delete')
            && $user->tenant_id === $resource->tenant_id
            && $user->canAccessBranch($resource->branch);
    }
}
