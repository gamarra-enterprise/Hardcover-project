<?php

namespace App\Http\Controllers\Payments;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\PaymentNotAllowed;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Payments\CardCharge;
use App\Payments\PaymentGateway;
use App\Payments\PaymentUnavailable;
use App\Services\PaymentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Paying with a card typed in the shop's own page.
 *
 * The page shows the fields of the gateway (its own secure fields, served by the gateway) and the
 * browser turns what was typed into a one-time token. This controller receives only that token and
 * who pays, never the card number or the security code, so the shop stays out of the scope of the
 * card industry's security standard (PCI DSS).
 */
class CardPaymentController extends Controller
{
    /** Names the shop never accepts: a form posting a card number would be a mistake worth stopping. */
    private const FORBIDDEN_FIELDS = ['card_number', 'cardnumber', 'number', 'pan', 'cvv', 'cvc', 'cvv2', 'security_code', 'securitycode', 'card_cvv'];

    public function show(Request $request, Order $order, PaymentGateway $gateway): View|RedirectResponse
    {
        $this->authorizeAccess($request, $order);

        if ($order->status !== OrderStatus::PENDING || ! $gateway->supportsCards()) {
            return redirect($order->signedUrl())->with('payment_error', 'Este pedido no se puede pagar con tarjeta en este momento.');
        }

        return view('payments.card', ['order' => $order, 'config' => $gateway->cardFormConfig()]);
    }

    public function store(Request $request, Order $order, PaymentService $payments): JsonResponse
    {
        $this->authorizeAccess($request, $order);

        if ($this->carriesCardData($request)) {
            Log::warning('A request with card data was sent to the shop for order '.$order->tracking_code.' and was refused.');

            return response()->json(['message' => 'No enviamos datos de tarjeta a nuestro servidor. Recarga la página e inténtalo de nuevo.'], 422);
        }

        $data = $request->validate([
            'token' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9_\-]+$/'],
            'payment_method_id' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9_\-]+$/'],
            'issuer_id' => ['nullable', 'string', 'max:20', 'regex:/^\d+$/'],
            'installments' => ['required', 'integer', 'min:1', 'max:12'],
            'email' => ['required', 'email', 'max:255'],
            'identification_type' => ['required', 'in:DNI,CE'],
            'identification_number' => ['required', 'string', 'max:20', fn ($attribute, $value, $fail) => $this->validDocument($request->input('identification_type'), (string) $value) ?: $fail('El número de documento no es válido.')],
            'cardholder_name' => ['nullable', 'string', 'max:120'],
        ]);

        // Card testing (trying many numbers on a checkout) is slowed down: a few tries per order and address.
        $key = 'card:'.$order->id.':'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json(['message' => 'Hiciste demasiados intentos. Espera unos minutos antes de volver a intentarlo.'], 429);
        }
        RateLimiter::hit($key, 900);

        try {
            $payment = $payments->payWithCard($order, new CardCharge(
                token: $data['token'],
                paymentMethodId: $data['payment_method_id'],
                issuerId: $data['issuer_id'] ?? null,
                installments: (int) $data['installments'],
                email: $data['email'],
                identificationType: $data['identification_type'],
                identificationNumber: $data['identification_number'],
                cardholderName: $data['cardholder_name'] ?? null,
            ));
        } catch (PaymentNotAllowed $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        } catch (PaymentUnavailable $e) {
            report($e);

            // The charge may have gone through; the notification will confirm it if it did.
            return response()->json(['message' => 'No pudimos confirmar tu pago. Si se te cobró, lo confirmaremos y te avisaremos por correo; no vuelvas a pagar todavía.'], 502);
        }

        if (in_array($payment->status, [PaymentStatus::FAILED, PaymentStatus::CANCELLED], true)) {
            return response()->json(['status' => 'rejected', 'message' => $payments->rejectionMessage($payment)]);
        }

        session()->flash('payment_notice', $this->notice($order->fresh(), $payment));

        return response()->json(['status' => 'ok', 'redirect' => $order->signedUrl()]);
    }

    private function authorizeAccess(Request $request, Order $order): void
    {
        abort_unless($request->hasValidSignature() || $request->user()?->can('view', $order), 404);
    }

    private function notice(Order $order, Payment $payment): string
    {
        return match (true) {
            $order->status === OrderStatus::CONFIRMED => '¡Pago confirmado! Gracias por tu compra.',
            in_array($order->status, [OrderStatus::CANCELLED, OrderStatus::REFUNDED], true) => 'Tu pedido fue cancelado. Si ya pagaste, te devolveremos el dinero.',
            $payment->status === PaymentStatus::PROCESSING => 'Estamos verificando tu pago. Te avisaremos por correo cuando se confirme.',
            default => 'Todavía no recibimos la confirmación de tu pago. Si ya pagaste, te avisaremos por correo.',
        };
    }

    private function validDocument(?string $type, string $number): bool
    {
        return match ($type) {
            'DNI' => (bool) preg_match('/^\d{8}$/', $number),
            'CE' => (bool) preg_match('/^[A-Za-z0-9]{9,12}$/', $number),
            default => false,
        };
    }

    /** True when the request looks like it carries a card number or a security code. */
    private function carriesCardData(Request $request): bool
    {
        // Arr::dot also reaches fields nested at any depth ("payer.card_number").
        foreach (Arr::dot($request->all()) as $name => $value) {
            $last = strtolower((string) last(explode('.', (string) $name)));

            if (in_array($last, self::FORBIDDEN_FIELDS, true)) {
                return true;
            }

            if (is_string($value) && $this->looksLikeCardNumber($value)) {
                return true;
            }
        }

        return false;
    }

    /** Thirteen to nineteen digits that pass the Luhn check, typed with or without spaces and dashes. */
    private function looksLikeCardNumber(string $value): bool
    {
        $digits = preg_replace('/[\s\-]/', '', $value);

        if (! preg_match('/^\d{13,19}$/', (string) $digits)) {
            return false;
        }

        $sum = 0;
        foreach (array_reverse(str_split($digits)) as $i => $digit) {
            $n = (int) $digit * ($i % 2 === 1 ? 2 : 1);
            $sum += $n > 9 ? $n - 9 : $n;
        }

        return $sum % 10 === 0;
    }
}
