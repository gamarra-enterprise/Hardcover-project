<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

/**
 * Anyone, guests included, can browse active products. Only staff manage the catalog.
 */
class ProductPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Product $product): bool
    {
        return $product->is_active || (bool) $user?->isStaff();
    }

    public function create(User $user): bool
    {
        return $user->isStaff();
    }

    public function update(User $user, Product $product): bool
    {
        return $user->isStaff();
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->isStaff();
    }
}
