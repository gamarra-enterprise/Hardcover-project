<?php

namespace App\Policies;

use App\Models\User;

/**
 * Managing accounts and roles belongs to the super admin, who passes through Gate::before.
 * Everyone else may only see and edit their own account.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, User $model): bool
    {
        return $user->is($model);
    }

    public function update(User $user, User $model): bool
    {
        return $user->is($model);
    }

    public function delete(User $user, User $model): bool
    {
        return false;
    }

    /** Changing anyone's role, including your own, is a super admin action. */
    public function changeRole(User $user, User $model): bool
    {
        return false;
    }
}
