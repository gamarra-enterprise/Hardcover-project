<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\OrderNotCancellable;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly PaymentService $payments,
    ) {}

    /**
     * Cancel an order: it moves to "cancelled", everything the customer paid is set aside to be
     * returned in full, and the stock that had been deducted goes back to the shelf. The money
     * itself is returned through the gateway right after, and the order moves to "refunded" when it is.
     *
     * Cancelling is possible only while the status allows it (before the order ships). Whether this
     * person may cancel this order is for the caller to authorize (see OrderPolicy).
     *
     * @return ?string the amount to return, or null if nothing had been paid
     *
     * @throws OrderNotCancellable
     */
    public function cancel(Order $order, ?User $by = null, ?string $reason = null): ?string
    {
        $refund = DB::transaction(function () use ($order, $by, $reason) {
            $locked = Order::lockForUpdate()->findOrFail($order->id);

            if (! $locked->status->customerCanCancel()) {
                throw new OrderNotCancellable($locked->status);
            }

            $paid = (string) $locked->payments()->where('status', PaymentStatus::COMPLETED->value)->sum('amount');
            $refund = bccomp($paid, '0', 2) > 0 ? bcadd($paid, '0', 2) : null;

            $locked->forceFill(['refund_amount' => $refund])->save();
            $locked->transitionTo(OrderStatus::CANCELLED, $by, $reason, notify: false);
            $this->inventory->restoreForOrder($locked);

            return $refund;
        });

        // The e-mail goes out once everything above has been saved.
        $order->refresh()->notifyStatus(OrderStatus::CANCELLED);

        // The money goes back through the gateway. If that fails the order stays cancelled with its
        // refund pending, and `payments:retry-refunds` tries again.
        if ($refund !== null) {
            $this->payments->refundOrder($order);
        }

        return $refund;
    }
}
