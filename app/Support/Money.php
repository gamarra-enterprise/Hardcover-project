<?php

namespace App\Support;

class Money
{
    /** Soles with two decimals, as shown in the shop: "S/ 1,234.50". */
    public static function format(string|int|float $amount): string
    {
        return 'S/ '.number_format((float) $amount, 2);
    }

    /**
     * $amount * $numerator / $denominator, rounded half up to the cent. bcmath alone truncates,
     * which loses a cent on amounts such as 33.33 at 90 %.
     */
    public static function fraction(string|int|float $amount, int|string $numerator, int|string $denominator): string
    {
        $exact = bcdiv(bcmul((string) $amount, (string) $numerator, 6), (string) $denominator, 6);

        return bcadd($exact, '0.005', 2);
    }
}
