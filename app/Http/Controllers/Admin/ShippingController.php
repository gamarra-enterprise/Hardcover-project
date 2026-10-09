<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\ShippingCostBelowMinimum;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Setting;
use App\Models\ShippingDistrict;
use App\Models\ShippingZone;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Shipping rates: the minimum of each zone and the cost of each district. The rule that a
 * district never costs less than its zone's minimum lives in the models; here it only shows as a message.
 */
class ShippingController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', ShippingZone::class);

        $zones = ShippingZone::with(['districts' => fn ($q) => $q->orderBy('name')])->orderBy('position')->get();

        return view('admin.shipping.index', ['zones' => $zones, 'freeFrom' => config('shop.free_shipping_from')]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        Gate::authorize('create', ShippingZone::class);

        $data = $request->validate(['free_shipping_from' => ['required', 'numeric', 'min:0', 'max:99999']]);

        Setting::put('free_shipping_from', number_format((float) $data['free_shipping_from'], 2, '.', ''));
        ActivityLog::record('shipping.free_from_updated', 'Envío gratis desde S/ '.number_format((float) $data['free_shipping_from'], 2));

        return back()->with('notice', 'Envío gratis actualizado.');
    }

    public function updateZone(Request $request, ShippingZone $zone): RedirectResponse
    {
        Gate::authorize('update', $zone);

        $data = $request->validate(['min_cost' => ['required', 'numeric', 'min:0', 'max:9999.99']]);

        $zone->update(['min_cost' => $data['min_cost'], 'is_active' => $request->boolean('is_active')]);

        ActivityLog::record('shipping.zone_updated', "Zona {$zone->name}: mínimo S/ {$zone->min_cost}".($zone->is_active ? '' : ' (inactiva)'));

        return back()->with('notice', 'Zona actualizada: '.$zone->name.'.');
    }

    public function storeDistrict(Request $request, ShippingZone $zone): RedirectResponse
    {
        Gate::authorize('create', ShippingDistrict::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'ubigeo' => ['required', 'digits:6', 'unique:shipping_districts,ubigeo'],
            'cost' => ['required', 'numeric', 'min:0', 'max:9999.99'],
        ]);

        try {
            $zone->districts()->create($data + ['is_active' => true]);
        } catch (ShippingCostBelowMinimum $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        ActivityLog::record('shipping.district_created', "Distrito agregado: {$data['name']} a S/ ".number_format((float) $data['cost'], 2));

        return back()->with('notice', 'Distrito agregado.');
    }

    public function updateDistrict(Request $request, ShippingDistrict $district): RedirectResponse
    {
        Gate::authorize('update', $district);

        $data = $request->validate(['cost' => ['required', 'numeric', 'min:0', 'max:9999.99']]);

        try {
            $district->update(['cost' => $data['cost'], 'is_active' => $request->boolean('is_active')]);
        } catch (ShippingCostBelowMinimum $e) {
            return back()->with('error', $e->getMessage());
        }

        ActivityLog::record('shipping.district_updated', "Tarifa de {$district->name}: S/ {$district->cost}".($district->is_active ? '' : ' (inactivo)'));

        return back()->with('notice', 'Tarifa de '.$district->name.' guardada.');
    }
}
