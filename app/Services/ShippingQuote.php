<?php

namespace App\Services;

use App\Models\ShippingDistrict;

final readonly class ShippingQuote
{
    public function __construct(
        public ShippingDistrict $district,
        public string $baseCost,
        public string $cost,
        public bool $isFree,
    ) {}
}
