<?php

namespace App\Payments;

use App\Enums\PaymentStatus;

/** A payment as reported by the gateway. */
final readonly class GatewayPayment
{
    /**
     * @param  string  $amount  what was charged, with two decimals
     * @param  ?string  $orderCode  the tracking code the shop sent when the checkout was opened
     * @param  array<string, mixed>  $raw  the gateway's answer, kept for audit
     * @param  ?string  $detail  why the gateway rejected it, when it did
     */
    public function __construct(
        public string $id,
        public PaymentStatus $status,
        public string $amount,
        public string $currency,
        public ?string $orderCode,
        public array $raw = [],
        public ?string $detail = null,
    ) {}
}
