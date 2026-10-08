<?php

namespace App\Policies;

use App\Models\Cart;
use App\Models\User;

/**
 * Covers carts of logged-in users. Guest carts are tied to the session, not to a user.
 */
class CartPolicy
{
    public function view(User $user, Cart $cart): bool
    {
        return $cart->user_id === $user->id;
    }

    public function update(User $user, Cart $cart): bool
    {
        return $cart->user_id === $user->id;
    }
}
