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
        return $user->can('employees.update') && $user->tenant_id === $employeeProfile->tenant_id;
    }

    public function delete(User $user, EmployeeProfile $employeeProfile): bool
    {
        return $user->can('employees.delete') && $user->tenant_id === $employeeProfile->tenant_id;
    }
}
