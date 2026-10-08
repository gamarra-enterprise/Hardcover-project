<?php

namespace App\Support;

class Money
{
    /** Soles with two decimals, as shown in the shop: "S/ 1,234.50". */
    public static function format(string|int|float $amount): string
    {
        return 'S/ '.number_format((float) $amount, 2);
    }
}
