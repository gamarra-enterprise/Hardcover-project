<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A cart belongs to a user or, for guests, to a session id.
 */
#[Fillable(['user_id', 'session_id'])]
class Cart extends Model
{
    use HasFactory;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    /** Cart total with IGV included, using current product prices. */
    public function total(): string
    {
        return $this->items->loadMissing('product')->reduce(
            fn (string $sum, CartItem $item) => bcadd($sum, $item->lineTotal(), 2),
            '0.00',
        );
    }
}
