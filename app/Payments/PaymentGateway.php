<?php

namespace App\Payments;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;

/**
 * What the shop needs from a payment gateway. The rest of the system talks to this contract, so the
 * gateway can be changed, or faked in local development, without touching the order logic.
 */
interface PaymentGateway
{
    /** Name saved in payments.provider. */
    public function name(): string;

    /**
     * Open a checkout for the order and return where to send the customer.
     *
     * @throws PaymentUnavailable when the gateway cannot be reached or is not set up
     */
    public function createCheckout(Order $order, Payment $payment): GatewayCheckout;

    /**
     * The payment as the gateway has it now. The gateway is the source of truth: the shop never
     * trusts the status that arrives in a notification, it asks.
     *
     * @throws PaymentUnavailable
     */
    public function fetchPayment(string $paymentId): ?GatewayPayment;

    /**
     * Return the money of a payment.
     *
     * @throws PaymentUnavailable
     */
    public function refund(string $paymentId, string $amount): bool;

    /**
     * The id of the payment a notification talks about, or null when it is about something the
     * shop ignores.
     *
     * @throws InvalidWebhook when the notification does not come from the gateway
     */
    public function paymentIdFromWebhook(Request $request): ?string;
}
