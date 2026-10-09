<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Exceptions\PaymentNotAllowed;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Pay by bank transfer: the customer transfers to the shop's account and uploads the proof; a person
 * confirms it in the panel (PaymentService::settleManual). Proofs are private files: they show bank data.
 */
class BankTransferService
{
    public function __construct(private readonly PaymentService $payments) {}

    public function enabled(): bool
    {
        return filled(config('shop.bank_transfer.account'));
    }

    /**
     * Register the proof. The payment waits as "processing" until staff review it.
     *
     * @throws PaymentNotAllowed when the order cannot be paid now or transfers are not offered
     */
    public function submitProof(Order $order, UploadedFile $proof, ?string $note = null): Payment
    {
        if (! $this->enabled()) {
            throw new PaymentNotAllowed('Por ahora no recibimos transferencias.');
        }

        return DB::transaction(function () use ($order, $proof, $note) {
            // One proof at a time: lock the order so a double click cannot register two.
            $locked = Order::lockForUpdate()->findOrFail($order->id);
            $this->payments->assertCanPay($locked);

            return $locked->payments()->create([
                'provider' => PaymentService::MANUAL_PROVIDER,
                'external_reference' => 'transfer-'.Str::uuid(),
                'amount' => $locked->total,
                'currency' => 'PEN',
                'status' => PaymentStatus::PROCESSING,
                'payload' => ['proof' => $proof->store('transfer-proofs', 'local'), 'note' => $note ? Str::limit($note, 200, '') : null],
            ]);
        });
    }

    /** Absolute path of a payment's proof, or null when it has none. */
    public function proofPath(Payment $payment): ?string
    {
        $path = $payment->provider === PaymentService::MANUAL_PROVIDER ? ($payment->payload['proof'] ?? null) : null;

        return $path && Storage::disk('local')->exists($path) ? Storage::disk('local')->path($path) : null;
    }
}
