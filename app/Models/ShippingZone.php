<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A group of districts (for example Lima Metropolitana) with the lowest price they may charge.
 */
#[Fillable(['name', 'slug', 'min_cost', 'position', 'is_active'])]
class ShippingZone extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        // Raising the minimum of a zone lifts every district that was below it.
        static::saved(function (ShippingZone $zone) {
            if ($zone->wasChanged('min_cost')) {
                $zone->districts()->where('cost', '<', $zone->min_cost)->update(['cost' => $zone->min_cost]);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['min_cost' => 'decimal:2', 'is_active' => 'boolean'];
    }

    public function districts(): HasMany
    {
        return $this->hasMany(ShippingDistrict::class);
    }
}
