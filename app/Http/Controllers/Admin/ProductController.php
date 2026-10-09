<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Models\BookDetail;
use App\Models\Category;
use App\Models\Product;
use App\Services\CatalogExporter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Validation\Rule;

/**
 * Catalog management. Products are hidden, never deleted: orders keep a copy of what was sold,
 * but a hidden product is the safe way to take something off the shop.
 */
class ProductController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Product::class);

        $search = trim((string) $request->query('q'));
        $status = ProductStatus::tryFrom((string) $request->query('estado'));

        $products = Product::query()
            ->with('bookDetail:product_id,author')
            ->when($status, fn ($q) => $q->where('status', $status->value))
            ->when($search !== '', function ($q) use ($search) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
                $q->where(fn ($q) => $q->where('name', 'ilike', $like)->orWhere('sku', 'ilike', $like)
                    ->orWhereHas('bookDetail', fn ($b) => $b->where('author', 'ilike', $like)->orWhere('isbn_13', 'ilike', $like)));
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.products.index', ['products' => $products, 'search' => $search, 'status' => $status]);
    }

    /** Backup of the catalog, in the same format the importer reads. */
    public function export(CatalogExporter $exporter): StreamedResponse
    {
        Gate::authorize('viewAny', Product::class);
        abort_unless(auth()->user()->isStaff(), 403);

        return response()->streamDownload(
            fn () => $exporter->write(fopen('php://output', 'w')),
            'catalogo-'.now()->format('Y-m-d').'.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    public function create(): View
    {
        Gate::authorize('create', Product::class);

        return view('admin.products.form', ['product' => new Product(['stock' => 0]), 'categories' => $this->categories()]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Product::class);

        $product = $this->save(new Product, $request);

        return redirect()->route('admin.products.edit', $product)->with('notice', 'Producto creado.');
    }

    public function edit(Product $product): View
    {
        Gate::authorize('update', $product);

        $product->load(['bookDetail', 'categories:id']);

        return view('admin.products.form', ['product' => $product, 'categories' => $this->categories()]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        Gate::authorize('update', $product);

        $this->save($product, $request);

        return back()->with('notice', 'Cambios guardados.');
    }

    private function save(Product $product, Request $request): Product
    {
        $data = $request->validate([
            'sku' => ['required', 'string', 'max:64', Rule::unique('products', 'sku')->ignore($product->id)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999.99'],
            'sale_price' => ['nullable', 'numeric', 'min:0', 'lt:price'],
            'cost_price' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
            'stock' => ['required', 'integer', 'min:0', 'max:100000'],
            'weight_grams' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'hidden' => ['nullable', 'boolean'],
            'image' => ['nullable', 'image', 'max:3072'],
            'categories' => ['nullable', 'array'],
            'categories.*' => ['integer', 'exists:categories,id'],
            'author' => ['nullable', 'string', 'max:255'],
            'isbn_13' => ['nullable', 'digits:13', Rule::unique('book_details', 'isbn_13')->ignore($product->id, 'product_id')],
            'publisher' => ['nullable', 'string', 'max:255'],
            'published_year' => ['nullable', 'integer', 'min:1400', 'max:2100'],
            'pages' => ['nullable', 'integer', 'min:1', 'max:20000'],
            'format' => ['nullable', 'string', 'max:30'],
        ]);

        DB::transaction(function () use ($product, $data, $request) {
            $product->fill(collect($data)->only(['sku', 'name', 'description', 'price', 'sale_price', 'cost_price', 'stock', 'weight_grams'])->all());
            // The model settles "active" or "out of stock" from the stock; here we only choose hidden or not.
            $product->status = $request->boolean('hidden') ? ProductStatus::HIDDEN : ProductStatus::ACTIVE;

            // The slug is set once: changing it later would break the links already shared.
            $product->slug ??= $this->uniqueSlug($data['name']);

            if ($request->hasFile('image')) {
                $old = $product->image_path;
                $product->image_path = $request->file('image')->store('products', 'public');
                $old && Storage::disk('public')->delete($old);
            }

            $product->save();
            $product->categories()->sync($data['categories'] ?? []);

            if (filled($data['author'] ?? null)) {
                BookDetail::updateOrCreate(['product_id' => $product->id], [
                    'author' => $data['author'],
                    'isbn_13' => $data['isbn_13'] ?? null,
                    'publisher' => $data['publisher'] ?? null,
                    'published_year' => $data['published_year'] ?? null,
                    'pages' => $data['pages'] ?? null,
                    'format' => $data['format'] ?? null,
                ]);
            } else {
                // No author means it is not a book (or the book data was cleared).
                BookDetail::where('product_id', $product->id)->delete();
            }
        });

        return $product;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'producto';
        $slug = $base;

        for ($i = 2; Product::where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }

    private function categories()
    {
        return Category::orderBy('name')->get(['id', 'name']);
    }
}
