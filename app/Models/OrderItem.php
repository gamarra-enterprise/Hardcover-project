<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['order_id', 'product_id', 'quantity', 'unit_price', 'product_snapshot'])]
class OrderItem extends Model
{
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'product_snapshot' => 'array',
        ];
    }

    /**
     * Build the item values from a product at purchase time. Always create items through
     * this method so every snapshot has the same keys.
     *
     * @return array<string, mixed>
     */
    public static function valuesFor(Product $product, int $quantity): array
    {
        $book = $product->bookDetail;

        return [
            'product_id' => $product->id,
            'quantity' => $quantity,
            'unit_price' => $product->currentPrice(),
            'product_snapshot' => [
                'sku' => $product->sku,
                'name' => $product->name,
                'slug' => $product->slug,
                'image_path' => $product->image_path,
                'weight_grams' => $product->weight_grams,
                'width_mm' => $product->width_mm,
                'height_mm' => $product->height_mm,
                'depth_mm' => $product->depth_mm,
                'author' => $book?->author,
                'publisher' => $book?->publisher,
                'isbn_13' => $book?->isbn_13,
            ],
        ];
    }

    public function lineTotal(): string
    {
        return bcmul((string) $this->unit_price, (string) $this->quantity, 2);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** Null when the product was deleted from the catalog; use product_snapshot then. */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
