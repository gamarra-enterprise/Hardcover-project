<?php

namespace App\Http\Controllers\Payments;

use App\Exceptions\PaymentNotAllowed;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Payments\PaymentUnavailable;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PayController extends Controller
{
    /** Send the customer to the gateway to pay a pending order. */
    public function __invoke(Request $request, Order $order, PaymentService $payments): RedirectResponse
    {
        // The button posts to a signed address, so a guest with the order link can pay and nobody else can.
        abort_unless($request->hasValidSignature() || $request->user()?->can('view', $order), 404);

        try {
            $checkout = $payments->start($order);
        } catch (PaymentNotAllowed $e) {
            return redirect($order->signedUrl())->with('payment_error', $e->getMessage());
        } catch (PaymentUnavailable $e) {
            report($e);

            return redirect($order->signedUrl())->with('payment_error', 'No pudimos iniciar el pago en este momento. Inténtalo de nuevo en unos minutos.');
        }

        return redirect()->away($checkout->url);
    }
}
