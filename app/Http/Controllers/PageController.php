<?php

namespace App\Http\Controllers;

use App\Models\ShippingZone;
use App\Services\ShippingService;
use Illuminate\Contracts\View\View;

class PageController extends Controller
{
    public function about(): View
    {
        return view('pages.about');
    }

    /** The answer about shipping is read from the real rates. */
    public function faq(ShippingService $shipping): View
    {
        return view('pages.faq', [
            'served' => $shipping->zonesWithDistricts()->pluck('name'),
            'zones' => ShippingZone::where('is_active', true)->orderBy('position')->get(),
            'freeFrom' => config('shop.free_shipping_from'),
        ]);
    }
}
