<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

/**
 * Payments are created by the gateway flow and never edited by hand. The only manual decisions are
 * on bank transfers (confirm, reject, mark a refund as sent), which staff make.
 */
class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function update(User $user, Payment $payment): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, Payment $payment): bool
    {
        return $user->isStaff() || $payment->order->user_id === $user->id;
    }
}
