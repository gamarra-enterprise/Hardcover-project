<?php

namespace App\Payments;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Throwable;

/**
 * Mercado Pago, through Checkout Pro: the customer is sent to a page of Mercado Pago, where the
 * methods enabled in the shop's account appear (cards, and Yape if it is enabled there), and
 * comes back. The payment is confirmed by asking the API, never by what the browser says.
 *
 * Written against the public API (preferences, payments, refunds and signed notifications) and
 * covered with simulated answers; it still has to be tried with real credentials.
 */
class MercadoPagoGateway implements PaymentGateway
{
    public function __construct(
        private readonly ?string $accessToken,
        private readonly ?string $webhookSecret,
        private readonly string $baseUrl = 'https://api.mercadopago.com',
    ) {}

    public function name(): string
    {
        return 'mercadopago';
    }

    public function createCheckout(Order $order, Payment $payment): GatewayCheckout
    {
        $returnUrl = URL::temporarySignedRoute('payments.return', now()->addDays(2), ['order' => $order->tracking_code]);

        $response = $this->send(fn (PendingRequest $http) => $http->post('/checkout/preferences', [
            'items' => $this->items($order),
            'external_reference' => $order->tracking_code,
            'payer' => array_filter(['email' => $order->contactEmail(), 'name' => $order->shipping_address['recipient_name'] ?? null]),
            'back_urls' => ['success' => $returnUrl, 'pending' => $returnUrl, 'failure' => $returnUrl],
            'auto_return' => 'approved',
            'notification_url' => route('payments.webhook'),
            'statement_descriptor' => 'HARDCOVER',
        ]));

        // Test credentials start with TEST- and get the sandbox address.
        $url = str_starts_with((string) $this->accessToken, 'TEST-')
            ? ($response['sandbox_init_point'] ?? $response['init_point'] ?? null)
            : ($response['init_point'] ?? null);

        if (! $url || empty($response['id'])) {
            throw new PaymentUnavailable('Mercado Pago did not return a checkout address.');
        }

        return new GatewayCheckout($url, (string) $response['id']);
    }

    public function fetchPayment(string $paymentId): ?GatewayPayment
    {
        $response = $this->send(fn (PendingRequest $http) => $http->get('/v1/payments/'.rawurlencode($paymentId)), allowNotFound: true);

        if ($response === null) {
            return null;
        }

        return new GatewayPayment(
            id: (string) $response['id'],
            status: $this->status((string) ($response['status'] ?? '')),
            amount: number_format((float) ($response['transaction_amount'] ?? 0), 2, '.', ''),
            currency: (string) ($response['currency_id'] ?? 'PEN'),
            orderCode: $response['external_reference'] ?? null,
            raw: $response,
        );
    }

    public function refund(string $paymentId, string $amount): bool
    {
        try {
            $this->send(fn (PendingRequest $http) => $http
                // The same key for the same payment: asking twice never refunds twice.
                ->withHeaders(['X-Idempotency-Key' => 'hardcover-refund-'.$paymentId])
                ->post('/v1/payments/'.rawurlencode($paymentId).'/refunds', ['amount' => (float) $amount]));

            return true;
        } catch (PaymentUnavailable $e) {
            // A refund that was already made answers with an error; check before giving up.
            if ($this->fetchPayment($paymentId)?->status === PaymentStatus::REFUNDED) {
                return true;
            }

            throw $e;
        }
    }

    public function paymentIdFromWebhook(Request $request): ?string
    {
        $id = $this->queryValue($request, 'data.id') ?? $request->input('data.id');

        $this->assertSignature($request, $id);

        $type = $request->input('type') ?? $request->query('type') ?? $request->query('topic');

        return $type === 'payment' && $id !== null && $id !== '' ? (string) $id : null;
    }

    /**
     * Mercado Pago signs "id:<data.id>;request-id:<x-request-id>;ts:<ts>;" with the shop's secret.
     * Without a configured secret nothing is accepted.
     */
    private function assertSignature(Request $request, mixed $id): void
    {
        if (! $this->webhookSecret) {
            throw new InvalidWebhook('The webhook secret is not configured.');
        }

        $header = (string) $request->header('x-signature', '');
        parse_str(str_replace(',', '&', $header), $parts);
        $ts = $parts['ts'] ?? null;
        $received = $parts['v1'] ?? null;

        if (! $ts || ! $received) {
            throw new InvalidWebhook('Missing signature.');
        }

        $manifest = '';
        if ($id !== null && $id !== '') {
            // The documentation asks to lowercase alphanumeric ids.
            $manifest .= 'id:'.(ctype_alnum((string) $id) ? strtolower((string) $id) : $id).';';
        }
        if ($requestId = $request->header('x-request-id')) {
            $manifest .= 'request-id:'.$requestId.';';
        }
        $manifest .= 'ts:'.$ts.';';

        if (! hash_equals(hash_hmac('sha256', $manifest, $this->webhookSecret), (string) $received)) {
            throw new InvalidWebhook('Invalid signature.');
        }
    }

    /** PHP turns "data.id" into "data_id" in the parsed query, so the raw query string is read. */
    private function queryValue(Request $request, string $key): ?string
    {
        foreach (explode('&', (string) $request->server->get('QUERY_STRING', '')) as $pair) {
            [$name, $value] = array_pad(explode('=', $pair, 2), 2, null);
            if (urldecode((string) $name) === $key) {
                return urldecode((string) $value);
            }
        }

        return null;
    }

    /** @return list<array<string, mixed>> */
    private function items(Order $order): array
    {
        $items = [];
        $sum = '0.00';

        foreach ($order->items as $item) {
            $items[] = [
                'id' => (string) ($item->product_snapshot['sku'] ?? $item->product_id),
                'title' => (string) ($item->product_snapshot['name'] ?? 'Producto'),
                'quantity' => $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'currency_id' => 'PEN',
            ];
            $sum = bcadd($sum, bcmul((string) $item->unit_price, (string) $item->quantity, 2), 2);
        }

        if (bccomp((string) $order->shipping_cost, '0', 2) > 0) {
            $items[] = ['id' => 'envio', 'title' => 'Envío', 'quantity' => 1, 'unit_price' => (float) $order->shipping_cost, 'currency_id' => 'PEN'];
            $sum = bcadd($sum, (string) $order->shipping_cost, 2);
        }

        // If the lines do not add up to the total, charge the total as a single item instead.
        if (bccomp($sum, (string) $order->total, 2) !== 0) {
            return [['id' => $order->tracking_code, 'title' => 'Pedido '.$order->tracking_code, 'quantity' => 1, 'unit_price' => (float) $order->total, 'currency_id' => 'PEN']];
        }

        return $items;
    }

    private function status(string $status): PaymentStatus
    {
        return match ($status) {
            'approved' => PaymentStatus::COMPLETED,
            'pending', 'in_process', 'in_mediation', 'authorized' => PaymentStatus::PROCESSING,
            'rejected' => PaymentStatus::FAILED,
            'cancelled' => PaymentStatus::CANCELLED,
            'refunded', 'charged_back' => PaymentStatus::REFUNDED,
            default => PaymentStatus::PROCESSING,
        };
    }

    /**
     * @param  callable(PendingRequest): Response  $call
     * @return array<string, mixed>|null
     */
    private function send(callable $call, bool $allowNotFound = false): ?array
    {
        if (! $this->accessToken) {
            throw new PaymentUnavailable('The Mercado Pago access token is not configured.');
        }

        try {
            $http = Http::baseUrl($this->baseUrl)->withToken($this->accessToken)->acceptJson()->asJson()->timeout(15);
            $response = $call($http);

            if ($allowNotFound && $response->status() === 404) {
                return null;
            }

            return $response->throw()->json() ?? [];
        } catch (RequestException $e) {
            // The answer may carry details of the request, so only the status is kept.
            throw new PaymentUnavailable('Mercado Pago answered with status '.$e->response->status().'.', previous: $e);
        } catch (Throwable $e) {
            if ($e instanceof PaymentUnavailable) {
                throw $e;
            }

            throw new PaymentUnavailable('Mercado Pago could not be reached.', previous: $e);
        }
    }
}
