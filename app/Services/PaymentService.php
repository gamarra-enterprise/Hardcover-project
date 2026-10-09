<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\InsufficientStock;
use App\Exceptions\PaymentNotAllowed;
use App\Models\Order;
use App\Models\Payment;
use App\Payments\CardCharge;
use App\Payments\GatewayCheckout;
use App\Payments\GatewayPayment;
use App\Payments\PaymentGateway;
use App\Payments\PaymentUnavailable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Takes an order from "pending payment" to "confirmed", whatever the gateway.
 *
 * - The gateway is the source of truth: a payment is processed from what the gateway says when
 *   asked, never from what a notification or the browser claims, and processing it again changes
 *   nothing.
 * - Stock goes down only when a payment is confirmed. If two customers pay for the last unit, the
 *   first payment confirmed keeps it; the other one is refunded in full and told why.
 * - A payment that cannot be used (late, duplicated, for another amount) is refunded.
 */
class PaymentService
{
    /** Provider name of payments confirmed by a person (bank transfer). */
    public const MANUAL_PROVIDER = 'bank_transfer';

    private const RETURN_REASON_NO_STOCK = 'Otra persona pagó antes la última unidad disponible de uno de los libros y ya no hay stock.';

    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly InventoryService $inventory,
    ) {}

    public function gateway(): PaymentGateway
    {
        return $this->gateway;
    }

    /**
     * Open the checkout of the gateway for a pending order.
     *
     * @throws PaymentNotAllowed when the order cannot be paid now
     * @throws PaymentUnavailable when the gateway fails
     */
    public function start(Order $order): GatewayCheckout
    {
        $this->assertCanPay($order);

        $payment = DB::transaction(function () use ($order) {
            // Attempts that never came back are left behind, not deleted.
            $order->payments()->where('status', PaymentStatus::PENDING->value)->update(['status' => PaymentStatus::CANCELLED->value]);

            return $order->payments()->create([
                'provider' => $this->gateway->name(),
                'external_reference' => 'attempt-'.Str::uuid(),
                'amount' => $order->total,
                'currency' => 'PEN',
                'status' => PaymentStatus::PENDING,
            ]);
        });

        try {
            $checkout = $this->gateway->createCheckout($order->loadMissing('items'), $payment);
        } catch (PaymentUnavailable $e) {
            $payment->update(['status' => PaymentStatus::FAILED]);
            Log::error('Could not open the checkout of '.$order->tracking_code.': '.$e->getMessage());

            throw $e;
        }

        $payment->update(['external_reference' => 'pref:'.$checkout->reference]);

        return $checkout;
    }

    /**
     * Charge a card from the token the browser got from the gateway, and process the answer. The card
     * number and code never come through here. Only one charge per order runs at a time, so a double
     * click cannot charge twice.
     *
     * @throws PaymentNotAllowed when the order cannot be paid now
     * @throws PaymentUnavailable when the gateway fails (the charge may still have gone through, and
     *                            the notification will settle it)
     */
    public function payWithCard(Order $order, CardCharge $card): Payment
    {
        if (! $this->gateway->supportsCards()) {
            throw new PaymentNotAllowed('El pago con tarjeta no está disponible por ahora.');
        }

        $lock = Cache::lock('pay-order:'.$order->id, 60);

        if (! $lock->get()) {
            throw new PaymentNotAllowed('Estamos procesando tu pago. Espera un momento.');
        }

        try {
            // Checked inside the lock: a second click finds the order already paid.
            $this->assertCanPay($order->refresh());

            $key = (string) Str::uuid();

            DB::transaction(function () use ($order, $key) {
                $order->payments()->where('status', PaymentStatus::PENDING->value)->update(['status' => PaymentStatus::CANCELLED->value]);
                $order->payments()->create([
                    'provider' => $this->gateway->name(),
                    'external_reference' => 'attempt-'.$key,
                    'amount' => $order->total,
                    'currency' => 'PEN',
                    'status' => PaymentStatus::PENDING,
                ]);
            });

            try {
                $remote = $this->gateway->chargeCard($order->loadMissing('items'), $card, $key);
            } catch (PaymentUnavailable $e) {
                Log::error('Card charge of '.$order->tracking_code.' got no answer: '.$e->getMessage());

                throw $e;
            }

            return $this->apply($order, $remote);
        } finally {
            $lock->release();
        }
    }

    /**
     * What is kept of the gateway's answer: enough to audit the payment, without the personal data it
     * carries (the payer's document and phone, the cardholder). The first six and last four digits of
     * the card stay, which is what the card industry allows to keep.
     *
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function forAudit(array $raw): array
    {
        Arr::forget($raw, ['payer.identification', 'payer.phone', 'payer.first_name', 'payer.last_name', 'card.cardholder', 'additional_info.payer']);

        return $raw;
    }

    /** What to tell the customer when the card was rejected, from the gateway's reason. */
    public function rejectionMessage(Payment $payment): string
    {
        return match ($payment->payload['status_detail'] ?? $payment->payload['detail'] ?? null) {
            'cc_rejected_insufficient_amount' => 'Tu tarjeta no tiene fondos suficientes.',
            'cc_rejected_bad_filled_card_number' => 'Revisa el número de tu tarjeta.',
            'cc_rejected_bad_filled_date' => 'Revisa la fecha de vencimiento.',
            'cc_rejected_bad_filled_security_code' => 'Revisa el código de seguridad (CVV).',
            'cc_rejected_bad_filled_other' => 'Revisa los datos de tu tarjeta.',
            'cc_rejected_call_for_authorize' => 'Tu banco necesita que autorices este pago. Llámalo e inténtalo de nuevo.',
            'cc_rejected_card_disabled' => 'Tu tarjeta está inactiva. Actívala o usa otra.',
            'cc_rejected_duplicated_payment' => 'Ya hiciste un pago igual hace poco. Revisa tu pedido antes de volver a pagar.',
            'cc_rejected_high_risk' => 'No pudimos aprobar el pago por seguridad. Prueba con otro medio de pago.',
            'cc_rejected_max_attempts' => 'Superaste el límite de intentos. Prueba con otra tarjeta.',
            default => 'Tu banco rechazó el pago. Prueba con otra tarjeta o medio de pago.',
        };
    }

    /**
     * Process a payment of the gateway by its id (from a notification or from the customer coming
     * back). Returns null when it is not one of ours.
     */
    public function sync(string $gatewayPaymentId, ?Order $onlyForOrder = null): ?Payment
    {
        $remote = $this->gateway->fetchPayment($gatewayPaymentId);

        if (! $remote || ! $remote->orderCode) {
            return null;
        }

        $order = Order::where('tracking_code', $remote->orderCode)->first();

        // When the customer comes back, only the payment of that order is looked at.
        if (! $order || ($onlyForOrder && ! $onlyForOrder->is($order))) {
            return null;
        }

        return $this->apply($order, $remote);
    }

    /** Return the money of every completed payment of an order. True when nothing is left to return. */
    public function refundOrder(Order $order): bool
    {
        $done = true;

        foreach ($order->payments()->where('status', PaymentStatus::COMPLETED->value)->get() as $payment) {
            $done = $this->refundPayment($payment) && $done;
        }

        return $done;
    }

    /** Return the money of one payment. When the gateway fails it stays pending, to be retried. */
    public function refundPayment(Payment $payment): bool
    {
        if ($payment->status === PaymentStatus::REFUNDED) {
            return true;
        }

        if ($payment->status !== PaymentStatus::COMPLETED) {
            return false;
        }

        // A bank transfer cannot be returned through the gateway: a person sends the money back
        // and marks it with markRefundedManually(). Until then the refund stays pending.
        if ($payment->provider === self::MANUAL_PROVIDER) {
            return false;
        }

        try {
            if (! $this->gateway->refund($payment->external_reference, (string) $payment->amount)) {
                return false;
            }
        } catch (PaymentUnavailable $e) {
            Log::error("Refund of payment {$payment->id} (order {$payment->order_id}) failed: ".$e->getMessage());

            return false;
        }

        $this->finishRefund($payment);

        return true;
    }

    /** Staff sent the money of a bank transfer back to the customer. */
    public function markRefundedManually(Payment $payment): bool
    {
        if ($payment->provider !== self::MANUAL_PROVIDER || $payment->status !== PaymentStatus::COMPLETED) {
            return false;
        }

        $this->finishRefund($payment);

        return true;
    }

    private function finishRefund(Payment $payment): void
    {
        $order = DB::transaction(function () use ($payment) {
            $payment->update(['status' => PaymentStatus::REFUNDED]);
            $order = Order::lockForUpdate()->findOrFail($payment->order_id);

            // The order is closed as refunded once nothing paid is left to return.
            if ($order->status === OrderStatus::CANCELLED && ! $order->payments()->where('status', PaymentStatus::COMPLETED->value)->exists()) {
                $order->transitionTo(OrderStatus::REFUNDED, null, 'Reembolso realizado', notify: false);

                return $order;
            }

            return null;
        });

        $order?->notifyStatus(OrderStatus::REFUNDED);
    }

    /**
     * Staff decide on a bank transfer whose proof the customer uploaded. Approving confirms the order
     * and takes the stock exactly like an approved gateway payment; rejecting leaves the order pending
     * so the customer can try again.
     *
     * @throws PaymentNotAllowed when the payment is no longer waiting for a decision
     */
    public function settleManual(Payment $payment, bool $approved, ?string $reason = null): Payment
    {
        /** @var array{notify: list<array{0: OrderStatus, 1: ?string}>, refund: list<Payment>} $after */
        $after = ['notify' => [], 'refund' => []];

        $payment = DB::transaction(function () use ($payment, $approved, $reason, &$after) {
            $order = Order::lockForUpdate()->findOrFail($payment->order_id);
            $payment = Payment::lockForUpdate()->findOrFail($payment->id);

            if ($payment->provider !== self::MANUAL_PROVIDER || $payment->status !== PaymentStatus::PROCESSING) {
                throw new PaymentNotAllowed('Este pago ya fue revisado.');
            }

            if (! $approved) {
                $payment->update(['status' => PaymentStatus::FAILED, 'payload' => [...($payment->payload ?? []), 'rejected_reason' => $reason]]);

                return $payment;
            }

            $payment->forceFill(['status' => PaymentStatus::COMPLETED, 'paid_at' => now()])->save();
            $this->confirm($order, $payment, $after);

            return $payment;
        });

        foreach ($after['notify'] as [$status, $why]) {
            Order::findOrFail($payment->order_id)->notifyStatus($status, $why);
        }

        foreach ($after['refund'] as $toRefund) {
            $this->refundPayment($toRefund);
        }

        return $payment;
    }

    /** Try again the refunds that failed. Returns how many orders ended fully refunded. */
    public function retryPendingRefunds(): int
    {
        $count = 0;

        $orders = Order::where('status', OrderStatus::CANCELLED->value)
            ->whereNotNull('refund_amount')
            ->whereHas('payments', fn ($q) => $q->where('status', PaymentStatus::COMPLETED->value))
            ->get();

        foreach ($orders as $order) {
            $count += $this->refundOrder($order) ? 1 : 0;
        }

        return $count;
    }

    /**
     * @throws PaymentNotAllowed
     */
    public function assertCanPay(Order $order): void
    {
        if ($order->status !== OrderStatus::PENDING) {
            throw new PaymentNotAllowed('Este pedido ya no está pendiente de pago.');
        }

        if ($order->payments()->where('status', PaymentStatus::PROCESSING->value)->exists()) {
            throw new PaymentNotAllowed('Estamos verificando tu pago. Te avisaremos por correo cuando se confirme.');
        }

        // Paying for something that is already gone would only end in a refund.
        foreach ($order->items()->with('product')->get() as $item) {
            if (! $item->product || $item->product->stock < $item->quantity) {
                throw new PaymentNotAllowed('Algunos productos de tu pedido ya no están disponibles. Puedes cancelar el pedido y volver a elegir.');
            }
        }
    }

    private function apply(Order $order, GatewayPayment $remote): Payment
    {
        /** @var array{notify: list<array{0: OrderStatus, 1: ?string}>, refund: list<Payment>} $after */
        $after = ['notify' => [], 'refund' => []];

        $payment = DB::transaction(function () use ($order, $remote, &$after) {
            // One payment of an order is processed at a time.
            $order = Order::lockForUpdate()->findOrFail($order->id);
            $payment = $this->bind($order, $remote);
            $before = $payment->status;

            $payment->fill([
                'amount' => $remote->amount,
                'currency' => $remote->currency,
                'payload' => $this->forAudit($remote->raw),
                'status' => $remote->status,
            ]);

            if ($remote->status === PaymentStatus::COMPLETED) {
                // The same notice again changes nothing.
                if (in_array($before, [PaymentStatus::COMPLETED, PaymentStatus::REFUNDED], true)) {
                    $payment->status = $before;
                    $payment->save();

                    return $payment;
                }

                $payment->paid_at = now();
                $payment->save();
                $this->confirm($order, $payment, $after);

                return $payment;
            }

            // A payment already completed is not undone by a later, weaker answer.
            if ($before === PaymentStatus::COMPLETED && $remote->status !== PaymentStatus::REFUNDED) {
                $payment->status = $before;
            }

            $payment->save();

            if ($remote->status === PaymentStatus::REFUNDED) {
                $this->closeRefunded($order, $payment, $after);
            }

            return $payment;
        });

        foreach ($after['notify'] as [$status, $reason]) {
            $order->refresh()->notifyStatus($status, $reason);
        }

        foreach ($after['refund'] as $toRefund) {
            $this->refundPayment($toRefund);
        }

        return $payment;
    }

    /** A payment was approved: confirm the order and take the stock, or decide it has to be refunded. */
    private function confirm(Order $order, Payment $payment, array &$after): void
    {
        $duplicate = $order->payments()->where('status', PaymentStatus::COMPLETED->value)->whereKeyNot($payment->id)->exists();

        if (bccomp((string) $payment->amount, (string) $order->total, 2) !== 0 || $payment->currency !== 'PEN') {
            Log::warning("Payment {$payment->id} of order {$order->tracking_code} was for {$payment->amount} {$payment->currency}, not {$order->total} PEN. It will be refunded.");
            $after['refund'][] = $payment;

            return;
        }

        if ($duplicate || $order->status === OrderStatus::CONFIRMED || ! in_array($order->status, [OrderStatus::PENDING, OrderStatus::CANCELLED], true)) {
            // The order was already paid: this second payment goes back to the customer.
            $after['refund'][] = $payment;

            return;
        }

        if ($order->status === OrderStatus::CANCELLED) {
            // Paid after cancelling: nothing to ship, everything goes back.
            $order->forceFill(['refund_amount' => $payment->amount])->save();
            $after['refund'][] = $payment;

            return;
        }

        try {
            $this->inventory->deductForOrder($order);
        } catch (InsufficientStock $e) {
            // Someone else's payment took the last units first.
            Log::notice("Order {$order->tracking_code} paid but out of stock: ".implode('; ', $e->shortages));

            $order->forceFill(['refund_amount' => $payment->amount])->save();
            $order->transitionTo(OrderStatus::CANCELLED, null, 'Sin stock al confirmar el pago', notify: false);
            $after['notify'][] = [OrderStatus::CANCELLED, self::RETURN_REASON_NO_STOCK];
            $after['refund'][] = $payment;

            return;
        }

        $order->transitionTo(OrderStatus::CONFIRMED, null, 'Pago confirmado', notify: false);
        $after['notify'][] = [OrderStatus::CONFIRMED, null];
    }

    /** The gateway says the money went back (a refund or a dispute). */
    private function closeRefunded(Order $order, Payment $payment, array &$after): void
    {
        if ($order->status === OrderStatus::CANCELLED && ! $order->payments()->where('status', PaymentStatus::COMPLETED->value)->exists()) {
            $order->transitionTo(OrderStatus::REFUNDED, null, 'Reembolso realizado', notify: false);
            $after['notify'][] = [OrderStatus::REFUNDED, null];

            return;
        }

        if (in_array($order->status, [OrderStatus::CONFIRMED, OrderStatus::PROCESSING, OrderStatus::SHIPPED, OrderStatus::DELIVERED], true)) {
            // Money returned from outside (a dispute, for instance) on an order that is moving on.
            Log::warning("Payment {$payment->id} of order {$order->tracking_code} was refunded outside the shop and the order is {$order->status->value}. Needs a person to look at it.");
        }
    }

    /**
     * Find the row that stands for this gateway payment: the one already holding its id, or the open
     * attempt of the order (created when the checkout was opened), or a new one.
     */
    private function bind(Order $order, GatewayPayment $remote): Payment
    {
        $provider = $this->gateway->name();

        $existing = Payment::where('provider', $provider)->where('external_reference', $remote->id)->first();
        if ($existing) {
            return $existing;
        }

        $attempt = $order->payments()
            ->where('provider', $provider)
            ->where(fn ($q) => $q->where('external_reference', 'like', 'pref:%')->orWhere('external_reference', 'like', 'attempt-%'))
            ->latest('id')
            ->first();

        if ($attempt) {
            $attempt->external_reference = $remote->id;

            return $attempt;
        }

        return new Payment([
            'order_id' => $order->id,
            'provider' => $provider,
            'external_reference' => $remote->id,
            'amount' => $remote->amount,
            'currency' => $remote->currency,
            'status' => PaymentStatus::PENDING,
        ]);
    }
}
