<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\PaymentNotAllowed;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\Payment;
use App\Services\BankTransferService;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Staff review of bank transfers: see the proof, confirm or reject, and mark a manual refund as sent. */
class TransferReviewController extends Controller
{
    public function proof(Payment $payment, BankTransferService $transfers): BinaryFileResponse
    {
        Gate::authorize('view', $payment);
        $path = $transfers->proofPath($payment) ?? abort(404);

        return response()->file($path, ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }

    public function confirm(Payment $payment, PaymentService $payments): RedirectResponse
    {
        Gate::authorize('update', $payment);

        try {
            $payments->settleManual($payment, true);
        } catch (PaymentNotAllowed $e) {
            return back()->with('error', $e->getMessage());
        }

        $order = Order::findOrFail($payment->order_id);
        ActivityLog::record('payment.transfer_confirmed', "Confirmó la transferencia del pedido {$order->tracking_code}", ['payment_id' => $payment->id]);

        return back()->with('notice', 'Pago confirmado. El cliente fue avisado.');
    }

    public function reject(Request $request, Payment $payment, PaymentService $payments): RedirectResponse
    {
        Gate::authorize('update', $payment);
        $data = $request->validate(['reason' => ['required', 'string', 'max:200']]);

        try {
            $payments->settleManual($payment, false, $data['reason']);
        } catch (PaymentNotAllowed $e) {
            return back()->with('error', $e->getMessage());
        }

        $order = Order::findOrFail($payment->order_id);
        ActivityLog::record('payment.transfer_rejected', "Rechazó la transferencia del pedido {$order->tracking_code}: {$data['reason']}", ['payment_id' => $payment->id]);

        return back()->with('notice', 'Comprobante rechazado. El cliente puede enviar otro.');
    }

    public function refunded(Payment $payment, PaymentService $payments): RedirectResponse
    {
        Gate::authorize('update', $payment);

        if (! $payments->markRefundedManually($payment)) {
            return back()->with('error', 'Este pago no admite reembolso manual.');
        }

        $order = Order::findOrFail($payment->order_id);
        ActivityLog::record('payment.transfer_refunded', "Marcó como devuelta la transferencia del pedido {$order->tracking_code}", ['payment_id' => $payment->id]);

        return back()->with('notice', 'Reembolso registrado.');
    }
}
