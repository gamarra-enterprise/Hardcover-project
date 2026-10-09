<?php

namespace App\Http\Controllers\Payments;

use App\Exceptions\PaymentNotAllowed;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\BankTransferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TransferController extends Controller
{
    /** The customer uploads the proof of the transfer. Like the pay button, it needs the signed address or the owner. */
    public function __invoke(Request $request, Order $order, BankTransferService $transfers): RedirectResponse
    {
        abort_unless($request->hasValidSignature() || $request->user()?->can('view', $order), 404);

        $data = $request->validate([
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'note' => ['nullable', 'string', 'max:200'],
        ]);

        try {
            $transfers->submitProof($order, $request->file('proof'), $data['note'] ?? null);
        } catch (PaymentNotAllowed $e) {
            return redirect($order->signedUrl())->with('payment_error', $e->getMessage());
        }

        return redirect($order->signedUrl())->with('payment_notice', 'Recibimos tu comprobante. Confirmaremos tu pago y te avisaremos por correo.');
    }
}
