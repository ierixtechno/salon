<?php

namespace App\Policies;

use App\Domain\Core\Models\ServiceCategory;
use App\Models\User;

class ServiceCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('services.view');
    }

    public function view(User $user, ServiceCategory $serviceCategory): bool
    {
        return $user->can('services.view') && $user->tenant_id === $serviceCategory->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->can('services.create');
    }

    public function update(User $user, ServiceCategory $serviceCategory): bool
    {
        return $user->can('services.update') && $user->tenant_id === $serviceCategory->tenant_id;
    }

    public function delete(User $user, ServiceCategory $serviceCategory): bool
    {
        return $user->can('services.delete') && $user->tenant_id === $serviceCategory->tenant_id;
    }
}
