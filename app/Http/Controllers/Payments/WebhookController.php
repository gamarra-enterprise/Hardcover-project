<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Payments\InvalidWebhook;
use App\Payments\MercadoPagoGateway;
use App\Payments\PaymentGateway;
use App\Payments\PaymentUnavailable;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Notifications of Mercado Pago. The notification only says "payment X changed": the payment is then
 * fetched from the gateway and processed from there, so a forged notice cannot confirm anything.
 */
class WebhookController extends Controller
{
    public function __invoke(Request $request, PaymentGateway $gateway, PaymentService $payments): JsonResponse
    {
        abort_unless($gateway instanceof MercadoPagoGateway, 404);

        try {
            $paymentId = $gateway->paymentIdFromWebhook($request);
        } catch (InvalidWebhook) {
            return response()->json(['error' => 'invalid signature'], 401);
        }

        if ($paymentId === null) {
            return response()->json(['ok' => true]);
        }

        try {
            $payments->sync($paymentId);
        } catch (PaymentUnavailable $e) {
            report($e);

            // Not a 2xx, so the gateway sends the notice again later.
            return response()->json(['error' => 'try again'], 503);
        }

        return response()->json(['ok' => true]);
    }
}
