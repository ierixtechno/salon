<?php

namespace App\Policies;

use App\Domain\Core\Models\Supplier;
use App\Models\User;

class SupplierPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('suppliers.view');
    }

    public function view(User $user, Supplier $supplier): bool
    {
        return $user->can('suppliers.view') && $user->tenant_id === $supplier->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->can('suppliers.create');
    }

    public function update(User $user, Supplier $supplier): bool
    {
        return $user->can('suppliers.update') && $user->tenant_id === $supplier->tenant_id;
    }

    /**
     * Aliases to update — destroy() only deactivates (CLAUDE.md §48), same
     * pattern as CustomerPolicy/BranchPolicy.
     */
    public function delete(User $user, Supplier $supplier): bool
    {
        return $this->update($user, $supplier);
    }
}
