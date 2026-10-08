<?php

namespace App\Models;

use App\Exceptions\ShippingCostBelowMinimum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A district the shop delivers to. Its cost can be raised freely but never set below the
 * minimum of its zone: that rule is enforced here so no screen or import can skip it.
 */
#[Fillable(['shipping_zone_id', 'name', 'ubigeo', 'cost', 'is_active'])]
class ShippingDistrict extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (ShippingDistrict $district) {
            $zone = $district->zone;

            if ($zone && bccomp((string) $district->cost, (string) $zone->min_cost, 2) < 0) {
                throw new ShippingCostBelowMinimum($zone->name, (string) $zone->min_cost);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['cost' => 'decimal:2', 'is_active' => 'boolean'];
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class, 'shipping_zone_id');
    }
}
