<?php

namespace App\Payments;

final readonly class GatewayCheckout
{
    public function __construct(public string $url, public string $reference) {}
}
