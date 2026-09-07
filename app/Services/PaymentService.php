<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use Exception;

class PaymentService
{
    public function processPayment(array $data): array
    {
        // TODO: Implement payment processing with Stripe / Mercado Pago
        throw new Exception('Payment processing not implemented');
    }

    public function handleWebhook(array $payload): array
    {
        // TODO: Implement webhook handling
        throw new Exception('Webhook handling not implemented');
    }

    public function refund(string $paymentId, float $amount = null): array
    {
        // TODO: Implement refund logic
        throw new Exception('Refund not implemented');
    }

    public function getPaymentStatus(string $paymentId): PaymentStatus
    {
        // TODO: Implement status retrieval
        throw new Exception('Status retrieval not implemented');
    }
}