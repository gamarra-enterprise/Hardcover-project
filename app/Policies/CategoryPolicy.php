<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

/**
 * Anyone, guests included, can browse active categories. Only staff manage categories.
 */
class CategoryPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Category $category): bool
    {
        return $category->is_active || (bool) $user?->isStaff();
    }

    public function create(User $user): bool
    {
        return $user->isStaff();
    }

    public function update(User $user, Category $category): bool
    {
        return $user->isStaff();
    }

    public function delete(User $user, Category $category): bool
    {
        return $user->isStaff();
    }
}
