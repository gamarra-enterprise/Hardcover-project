<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

/**
 * Customers see only their own orders; staff see and update all of them.
 * Placing an order is open to everyone, guests included, so there is no create rule here.
 */
class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, Order $order): bool
    {
        return $user->isStaff() || $order->user_id === $user->id;
    }

    public function update(User $user, Order $order): bool
    {
        return $user->isStaff();
    }

    public function delete(User $user, Order $order): bool
    {
        return false;
    }
}
