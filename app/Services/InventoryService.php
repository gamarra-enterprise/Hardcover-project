<?php

namespace App\Services;

use App\Enums\CartLineIssue;
use App\Enums\ProductStatus;
use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * Stock rules of the shop.
 *
 * - Putting a product in a cart never changes its stock and never blocks other buyers: several
 *   carts can hold the last unit at the same time.
 * - Stock goes down only when an order's payment is completed. That deduction (atomic, under a
 *   row lock, and aware that the last unit may already be sold) belongs to the payment step.
 */
class InventoryService
{
    /** Units that can be bought right now: the stock of a visible product, otherwise zero. */
    public function availableFor(Product $product): int
    {
        return match ($product->status) {
            ProductStatus::ACTIVE => max(0, (int) $product->stock),
            default => 0,
        };
    }

    /** What stops a buyer from taking $quantity units of this product, or null if nothing does. */
    public function issueFor(Product $product, int $quantity): ?CartLineIssue
    {
        if ($product->status === ProductStatus::HIDDEN) {
            return CartLineIssue::HIDDEN;
        }

        $available = $this->availableFor($product);

        return match (true) {
            $available === 0 => CartLineIssue::OUT_OF_STOCK,
            $quantity > $available => CartLineIssue::EXCEEDS_STOCK,
            default => null,
        };
    }
}
