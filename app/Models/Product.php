<?php

namespace App\Models;

use App\Enums\ProductStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'sku', 'name', 'slug', 'description', 'image_path', 'price', 'sale_price', 'cost_price', 'stock',
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
        ];
    }

    /** Products shown in the shop: active and out of stock, but not hidden. */
    public function scopeVisible(Builder $query): void
    {
        $query->where('status', '!=', ProductStatus::HIDDEN->value);
    }

    public function isVisible(): bool
    {
        return $this->status->isVisible();
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
