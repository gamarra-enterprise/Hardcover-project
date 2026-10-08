<?php

namespace App\Services;

use App\Enums\CartLineIssue;
use App\Enums\ProductStatus;
use App\Exceptions\InsufficientStock;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Stock rules of the shop.
 *
 * - Putting a product in a cart never changes its stock and never blocks other buyers: several
 *   carts can hold the last unit at the same time.
 * - Stock goes down only when an order's payment is completed (deductForOrder), and goes back up
 *   if that order is cancelled (restoreForOrder). Both lock the product rows, are all-or-nothing
 *   and can be repeated safely.
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

    /**
     * Take the units of a paid order out of stock. Meant to run when the payment is confirmed.
     * If any product no longer has enough units, nothing is deducted and InsufficientStock lists
     * the products, so the payment step can decide what to do with that order.
     *
     * @throws InsufficientStock
     */
    public function deductForOrder(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $order = Order::lockForUpdate()->findOrFail($order->id);

            if ($order->stock_deducted_at) {
                return;
            }

            $items = $order->items()->get();
            $products = $this->lockProducts($items->pluck('product_id')->filter());

            $shortages = [];
            foreach ($items as $item) {
                $product = $products->get($item->product_id);
                $name = $item->product_snapshot['name'] ?? 'Producto';

                if (! $product) {
                    $shortages[] = "{$name}: ya no existe";
                } elseif ($product->stock < $item->quantity) {
                    $shortages[] = "{$name}: pidió {$item->quantity}, quedan {$product->stock}";
                }
            }

            if ($shortages) {
                throw new InsufficientStock($shortages);
            }

            foreach ($items as $item) {
                $product = $products[$item->product_id];
                // save() instead of decrement() so the product status follows the new stock.
                $product->stock -= $item->quantity;
                $product->save();
            }

            $order->forceFill(['stock_deducted_at' => now()])->save();
        });
    }

    /** Give the units back, for an order whose stock had been deducted. Does nothing otherwise. */
    public function restoreForOrder(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $order = Order::lockForUpdate()->findOrFail($order->id);

            if (! $order->stock_deducted_at) {
                return;
            }

            $items = $order->items()->get();
            $products = $this->lockProducts($items->pluck('product_id')->filter());

            foreach ($items as $item) {
                // A product deleted in the meantime has nothing to give back to.
                if ($product = $products->get($item->product_id)) {
                    $product->stock += $item->quantity;
                    $product->save();
                }
            }

            $order->forceFill(['stock_deducted_at' => null])->save();
        });
    }

    /**
     * Lock the rows in id order, so two orders sharing products cannot wait on each other forever.
     *
     * @return Collection<int, Product>
     */
    private function lockProducts(Collection $ids): Collection
    {
        return Product::whereIn('id', $ids->unique())->orderBy('id')->lockForUpdate()->get()->keyBy('id');
    }
}
