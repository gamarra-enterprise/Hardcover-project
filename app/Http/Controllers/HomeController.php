<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        $newest = Product::query()
            ->visible()
            ->with(['bookDetail', 'categories'])
            ->latest()
            ->limit(8)
            ->get();

        return view('home', [
            'newest' => $newest,
            // The brand cover floats a few covers around the title.
            'floating' => $newest->take(4),
        ]);
    }
}
