<?php

namespace App\Http\Controllers;

use App\Models\ShippingZone;
use Illuminate\Contracts\View\View;

class PageController extends Controller
{
    public function about(): View
    {
        return view('pages.about');
    }

    /** The answer about shipping is read from the real rates. */
    public function faq(): View
    {
        return view('pages.faq', [
            'zones' => ShippingZone::where('is_active', true)->orderBy('position')->get(),
            'freeFrom' => config('shop.free_shipping_from'),
        ]);
    }
}
