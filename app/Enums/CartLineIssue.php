<?php

namespace App\Enums;

/** Why a line of the cart cannot be bought as it is. The cart never changes these silently. */
enum CartLineIssue: string
{
    case HIDDEN = 'hidden';
    case OUT_OF_STOCK = 'out_of_stock';
    case EXCEEDS_STOCK = 'exceeds_stock';

    public function message(int $available): string
    {
        return match ($this) {
            self::HIDDEN => 'Este producto ya no está disponible.',
            self::OUT_OF_STOCK => 'Este producto se quedó sin stock.',
            self::EXCEEDS_STOCK => $available === 1 ? 'Solo queda 1 unidad.' : "Solo quedan {$available} unidades.",
        };
    }
}
