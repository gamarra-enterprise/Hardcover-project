<?php

namespace App\Payments;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * A stand-in for the real gateway, to try the whole flow without credentials: the customer lands on
 * a local page that approves or rejects the payment. It keeps its payments in the cache and is
 * refused in production (see AppServiceProvider).
 */
class FakeGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'fake';
    }

    public function createCheckout(Order $order, Payment $payment): GatewayCheckout
    {
        $id = 'FAKE-'.strtoupper(Str::random(10));

        Cache::put($this->key($id), ['status' => 'pending', 'amount' => (string) $order->total, 'order_code' => $order->tracking_code], now()->addDay());

        return new GatewayCheckout(
            URL::temporarySignedRoute('payments.fake.show', now()->addHours(2), ['paymentId' => $id]),
            'FAKE-PREF-'.strtoupper(Str::random(8)),
        );
    }

    public function fetchPayment(string $paymentId): ?GatewayPayment
    {
        $data = Cache::get($this->key($paymentId));

        if (! $data) {
            return null;
        }

        return new GatewayPayment(
            id: $paymentId,
            status: match ($data['status']) {
                'approved' => PaymentStatus::COMPLETED,
                'rejected' => PaymentStatus::FAILED,
                'refunded' => PaymentStatus::REFUNDED,
                default => PaymentStatus::PENDING,
            },
            amount: number_format((float) $data['amount'], 2, '.', ''),
            currency: 'PEN',
            orderCode: $data['order_code'],
            raw: $data,
        );
    }

    /** Approve or reject a payment, as the customer would on the gateway's page. */
    public function decide(string $paymentId, bool $approve): void
    {
        if ($data = Cache::get($this->key($paymentId))) {
            Cache::put($this->key($paymentId), [...$data, 'status' => $approve ? 'approved' : 'rejected'], now()->addDay());
        }
    }

    public function refund(string $paymentId, string $amount): bool
    {
        $data = Cache::get($this->key($paymentId));

        if (! $data) {
            throw new PaymentUnavailable('Unknown fake payment.');
        }

        Cache::put($this->key($paymentId), [...$data, 'status' => 'refunded'], now()->addDay());

        return true;
    }

    public function paymentIdFromWebhook(Request $request): ?string
    {
        throw new InvalidWebhook('The fake gateway sends no notifications.');
    }

    private function key(string $paymentId): string
    {
        return 'fake-payment:'.$paymentId;
    }
}
