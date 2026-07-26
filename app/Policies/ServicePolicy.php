<?php

namespace App\Policies;

use App\Domain\Core\Models\Service;
use App\Models\User;

class ServicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('services.view');
    }

    public function view(User $user, Service $service): bool
    {
        return $user->can('services.view') && $user->tenant_id === $service->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->can('services.create');
    }

    public function update(User $user, Service $service): bool
    {
        return $user->can('services.update') && $user->tenant_id === $service->tenant_id;
    }

    public function delete(User $user, Service $service): bool
    {
        return $user->can('services.delete') && $user->tenant_id === $service->tenant_id;
    }
}
