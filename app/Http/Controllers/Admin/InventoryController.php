<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** Stock at a glance: edit the count of a product right in the list. */
class InventoryController extends Controller
{
    /** At or below this many units a product is shown as running low. */
    public const LOW = 5;

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Product::class);
        abort_unless($request->user()->isStaff(), 403);

        $search = trim((string) $request->query('q'));
        $low = $request->boolean('bajo');

        $products = Product::query()
            ->when($low, fn ($q) => $q->where('stock', '<=', self::LOW))
            ->when($search !== '', function ($q) use ($search) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
                $q->where(fn ($q) => $q->where('name', 'ilike', $like)->orWhere('sku', 'ilike', $like));
            })
            ->orderBy('stock')->orderBy('name')
            ->paginate(30)->withQueryString();

        return view('admin.inventory', ['products' => $products, 'search' => $search, 'low' => $low, 'lowAt' => self::LOW]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        Gate::authorize('update', $product);
        $data = $request->validate(['stock' => ['required', 'integer', 'min:0', 'max:100000']]);

        $before = $product->stock;
        // save() runs the model's rule that keeps "sin stock" in step with the count.
        $product->stock = $data['stock'];
        $product->save();

        if ($before !== $product->stock) {
            ActivityLog::record('product.stock_changed', "Stock de «{$product->name}»: {$before} → {$product->stock}", ['product_id' => $product->id]);
        }

        return back()->with('notice', "Stock de «{$product->name}» actualizado.");
    }
}
