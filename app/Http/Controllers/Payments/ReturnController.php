<?php

namespace App\Http\Controllers\Payments;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Payments\PaymentUnavailable;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReturnController extends Controller
{
    /** What the gateway appends to the address it sends the customer back to. */
    private const GATEWAY_PARAMETERS = [
        'collection_id', 'collection_status', 'payment_id', 'status', 'external_reference', 'payment_type',
        'merchant_order_id', 'preference_id', 'site_id', 'processing_mode', 'merchant_account_id',
    ];

    /**
     * Where the customer lands after paying. The address is signed, apart from the parameters the
     * gateway adds. The message shown comes from what the shop has recorded, never from the browser.
     */
    public function __invoke(Request $request, Order $order, PaymentService $payments): RedirectResponse
    {
        abort_unless($request->hasValidSignatureWhileIgnoring(self::GATEWAY_PARAMETERS), 404);

        // Without a public address the notification may never arrive, so the payment is looked up now too.
        if ($paymentId = $request->query('payment_id') ?? $request->query('collection_id')) {
            try {
                $payments->sync((string) $paymentId, onlyForOrder: $order);
            } catch (PaymentUnavailable $e) {
                report($e);
            }
        }

        return redirect($order->signedUrl())->with('payment_notice', $this->notice($order->fresh()));
    }

    private function notice(Order $order): string
    {
        $latest = $order->payments()->latest('id')->first();

        return match (true) {
            $order->status === OrderStatus::CONFIRMED => '¡Pago confirmado! Gracias por tu compra.',
            $order->status === OrderStatus::CANCELLED => 'Tu pedido fue cancelado. Si ya pagaste, te devolveremos el dinero.',
            $latest?->status === PaymentStatus::PROCESSING => 'Estamos verificando tu pago. Te avisaremos por correo cuando se confirme.',
            $latest?->status === PaymentStatus::FAILED => 'El pago no se completó. Puedes intentarlo de nuevo.',
            default => 'Todavía no recibimos la confirmación de tu pago. Si ya pagaste, te avisaremos por correo.',
        };
    }
}
