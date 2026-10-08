<?php

namespace App\Enums;

/**
 * Hidden is set by staff. Out of stock is kept in sync with the stock count by the Product
 * model, so a product with stock is never "sin stock" and one without stock never "activo".
 */
enum ProductStatus: string
{
    case ACTIVE = 'active';
    case HIDDEN = 'hidden';
    case OUT_OF_STOCK = 'out_of_stock';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Activo',
            self::HIDDEN => 'Oculto',
            self::OUT_OF_STOCK => 'Sin stock',
        };
    }

    /** Shown in the shop. Out-of-stock products stay visible, marked as sold out. */
    public function isVisible(): bool
    {
        return $this !== self::HIDDEN;
    }
}
