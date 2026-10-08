<?php

namespace App\Services;

use App\Models\ShippingDistrict;
use App\Models\ShippingZone;
use Illuminate\Support\Collection;

/**
 * Shipping is priced by destination district, not by weight: books in the sheet have no weight.
 * Orders from the free-shipping amount (config/shop.php) pay nothing.
 */
class ShippingService
{
    /**
     * Price to deliver an order of $subtotal (IGV included) to the district with this ubigeo,
     * or null when the shop does not deliver there.
     */
    public function quote(string $ubigeo, string $subtotal): ?ShippingQuote
    {
        $district = ShippingDistrict::with('zone')
            ->where('ubigeo', $ubigeo)
            ->where('is_active', true)
            ->whereHas('zone', fn ($q) => $q->where('is_active', true))
            ->first();

        if (! $district) {
            return null;
        }

        $free = (float) $subtotal >= (float) config('shop.free_shipping_from');

        return new ShippingQuote($district, (string) $district->cost, $free ? '0.00' : (string) $district->cost, $free);
    }

    /**
     * Districts the shop delivers to, grouped by zone, for the checkout selector.
     *
     * @return Collection<int, ShippingZone>
     */
    public function zonesWithDistricts(): Collection
    {
        return ShippingZone::query()
            ->where('is_active', true)
            ->with(['districts' => fn ($q) => $q->where('is_active', true)->orderBy('name')])
            ->orderBy('position')
            ->get()
            ->filter(fn (ShippingZone $zone) => $zone->districts->isNotEmpty())
            ->values();
    }
}
