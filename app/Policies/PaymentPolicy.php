<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

/**
 * Payments are created by the gateway flow and never edited by hand.
 */
class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, Payment $payment): bool
    {
        return $user->isStaff() || $payment->order->user_id === $user->id;
    }
}
