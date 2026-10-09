<?php

namespace App\Http\Controllers;

use App\Enums\ProductType;
use App\Models\Category;
use App\Models\Product;
use App\Services\ShippingService;
use App\Support\MenuGenres;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    /** Moods that lead to a genre; only those whose genre exists on the shelf are shown. */
    private const MOODS = [
        ['Quiero perderme en una historia', 'novela', 'Narrativa que no te deja soltarla.', 'var(--grad-soft)', 'var(--ink)'],
        ['Algo corto para esta noche', 'poesia', 'Versos para leer en diez minutos.', '#0D100C', '#fff'],
        ['Quiero aprender algo nuevo', 'ensayo', 'Ideas grandes en pocas páginas.', 'var(--accent)', 'var(--accent-ink)'],
        ['Mejorar mis hábitos', 'desarrollo-personal', 'Pequeños cambios, grandes días.', 'var(--surface)', 'var(--ink)'],
        ['Leer con alguien pequeño', 'infantil', 'Clásicos para compartir en voz alta.', 'linear-gradient(135deg,#FFFFFF,#E3F9CF)', 'var(--ink)'],
    ];

    public function __invoke(ShippingService $shipping): View
    {
        $shelf = fn () => Product::query()->visible()->with(['bookDetail', 'categories']);

        $newest = $shelf()->latest()->orderBy('id')->limit(8)->get();
        $best = $shelf()->bestSelling()->limit(8)->get();

        $moods = collect(self::MOODS)
            ->map(function (array $mood) use ($shelf) {
                $category = Category::where('slug', $mood[1])->where('is_active', true)->first();

                return $category ? [...$mood, 'name' => $category->name, 'covers' => $shelf()->whereHas('categories', fn ($q) => $q->whereKey($category->id))->orderByRaw('(image_path is null)')->limit(3)->get()] : null;
            })
            ->filter(fn ($m) => $m && $m['covers']->isNotEmpty())
            ->values();

        return view('home', [
            'newest' => $newest,
            'best' => $best,
            // The brand cover floats a few covers around the title.
            'floating' => $newest->take(4),
            'featured' => $best->concat($newest)->unique('id')->take(4)->values(),
            'genres' => MenuGenres::all(7),
            'moods' => $moods,
            'gifts' => $shelf()->where('type', '!=', ProductType::BOOK->value)->where('stock', '>', 0)->latest()->limit(5)->get(),
            'shelfBooks' => $shelf()->where('type', ProductType::BOOK->value)->orderByRaw('(image_path is null)')->orderBy('id')->limit(12)->get(),
            'served' => $shipping->zonesWithDistricts()->pluck('name'),
            'freeFrom' => config('shop.free_shipping_from'),
            'transfer' => filled(config('shop.bank_transfer.account')),
        ]);
    }
}
