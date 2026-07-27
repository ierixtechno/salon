<?php

namespace App\Policies;

use App\Domain\Core\Models\ProductCategory;
use App\Models\User;

class ProductCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('products.view');
    }

    public function view(User $user, ProductCategory $productCategory): bool
    {
        return $user->can('products.view') && $user->tenant_id === $productCategory->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->can('products.create');
    }

    public function update(User $user, ProductCategory $productCategory): bool
    {
        return $user->can('products.update') && $user->tenant_id === $productCategory->tenant_id;
    }

    public function delete(User $user, ProductCategory $productCategory): bool
    {
        return $user->can('products.delete') && $user->tenant_id === $productCategory->tenant_id;
    }
}
