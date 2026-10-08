<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id', 'type', 'recipient_name', 'phone', 'line1', 'line2', 'city', 'state',
    'ubigeo', 'postal_code', 'country', 'latitude', 'longitude', 'is_default',
])]
class Address extends Model
{
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Immutable copy stored in orders.shipping_address. Orders never read the live address again.
     *
     * @return array<string, mixed>
     */
    public function toSnapshot(): array
    {
        return $this->only([
            'recipient_name', 'phone', 'line1', 'line2', 'city', 'state', 'ubigeo', 'postal_code', 'country',
        ]);
    }
}
