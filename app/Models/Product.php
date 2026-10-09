<?php

namespace App\Models;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'sku', 'name', 'slug', 'type', 'description', 'image_path', 'price', 'sale_price', 'cost_price', 'stock',
    'weight_grams', 'width_mm', 'height_mm', 'depth_mm', 'status',
])]
/** cost_price is what the store paid: hidden from serialization so it never reaches the shop. */
#[Hidden(['cost_price'])]
class Product extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        // Keep "sin stock" in sync with the stock count. Always change stock through save() or
        // update(), never with decrement() or raw queries, or this hook is skipped.
        static::saving(function (Product $product) {
            if ($product->status !== ProductStatus::HIDDEN) {
                $product->status = $product->stock > 0 ? ProductStatus::ACTIVE : ProductStatus::OUT_OF_STOCK;
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'status' => ProductStatus::class,
            'type' => ProductType::class,
        ];
    }

    /** Products shown in the shop: active and out of stock, but not hidden. */
    public function scopeVisible(Builder $query): void
    {
        $query->where('status', '!=', ProductStatus::HIDDEN->value);
    }

    /** Units sold in orders whose payment was confirmed and that were not cancelled or refunded afterwards, as a SQL expression. */
    public const SOLD_UNITS_SQL = "(select coalesce(sum(oi.quantity), 0) from order_items oi join orders o on o.id = oi.order_id where oi.product_id = products.id and o.status in ('confirmed','processing','shipped','delivered') and coalesce(o.stock_deducted_at, o.created_at) >= now() - interval '90 days')";

    /** Best sellers of the last 90 days first, only products that sold something. */
    public function scopeBestSelling(Builder $query): void
    {
        $query->whereRaw(self::SOLD_UNITS_SQL.' > 0')->orderByRaw(self::SOLD_UNITS_SQL.' desc');
    }

    /** Width over height of the cover, kept within sane limits so a very wide or very tall book cannot break the grid. */
    public function coverRatio(): float
    {
        if (! $this->width_mm || ! $this->height_mm) {
            return 2 / 3;
        }

        return round(max(0.55, min(1.3, $this->width_mm / $this->height_mm)), 3);
    }

    public function isVisible(): bool
    {
        return $this->status->isVisible();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function bookDetail(): HasOne
    {
        return $this->hasOne(BookDetail::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    /** Unit price actually charged (IGV included): the sale price when there is one. */
    public function currentPrice(): string
    {
        return (string) ($this->sale_price ?? $this->price);
    }

    /** Public URL of the cover, or null when the product has no image yet. */
    public function imageUrl(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }

    public function isBook(): bool
    {
        return $this->bookDetail()->exists();
    }
}
