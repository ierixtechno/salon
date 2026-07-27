<?php

namespace App\Policies;

use App\Domain\Core\Models\Package;
use App\Models\User;

class PackagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('packages.view');
    }

    public function view(User $user, Package $package): bool
    {
        return $user->can('packages.view') && $user->tenant_id === $package->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->can('packages.create');
    }

    public function update(User $user, Package $package): bool
    {
        return $user->can('packages.update') && $user->tenant_id === $package->tenant_id;
    }

    public function delete(User $user, Package $package): bool
    {
        return $user->can('packages.delete') && $user->tenant_id === $package->tenant_id;
    }
}
