<?php

namespace App\Policies;

use App\Domain\Core\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('products.view');
    }

    public function view(User $user, Product $product): bool
    {
        return $user->can('products.view') && $user->tenant_id === $product->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->can('products.create');
    }

    public function update(User $user, Product $product): bool
    {
        return $user->can('products.update') && $user->tenant_id === $product->tenant_id;
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->can('products.delete') && $user->tenant_id === $product->tenant_id;
    }
}
