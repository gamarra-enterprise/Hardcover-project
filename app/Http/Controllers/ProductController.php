<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class ProductController extends Controller
{
    public function show(Product $product): View
    {
        // A hidden product looks like it does not exist, except for the staff.
        abort_unless(Gate::allows('view', $product), 404);

        $product->load(['bookDetail', 'categories']);

        $related = Product::visible()
            ->whereKeyNot($product->id)
            ->whereHas('categories', fn ($q) => $q->whereIn('categories.id', $product->categories->modelKeys()))
            ->with(['bookDetail', 'categories'])
            ->inRandomOrder()
            ->limit(4)
            ->get();

        return view('shop.product', ['product' => $product, 'related' => $related]);
    }
}
