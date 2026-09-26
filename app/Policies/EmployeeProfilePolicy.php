<?php

namespace App\Policies;

use App\Domain\Core\Models\EmployeeProfile;
use App\Models\User;

class EmployeeProfilePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('employees.view');
    }

    public function view(User $user, EmployeeProfile $employeeProfile): bool
    {
        return $user->can('employees.view') && $user->tenant_id === $employeeProfile->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->can('employees.create');
    }

    public function update(User $user, EmployeeProfile $employeeProfile): bool
    {
        return $user->can('employees.update') && $user->tenant_id === $employeeProfile->tenant_id && $this->mayManage($user, $employeeProfile);
    }

    public function delete(User $user, EmployeeProfile $employeeProfile): bool
    {
        return $user->can('employees.delete') && $user->tenant_id === $employeeProfile->tenant_id && $this->mayManage($user, $employeeProfile);
    }

    /**
     * Only an Owner may manage an Owner (see User::isOwner).
     */
    private function mayManage(User $actor, EmployeeProfile $target): bool
    {
        return $actor->isOwner() || ! $target->user->isOwner();
    }
}
