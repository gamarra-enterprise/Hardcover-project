<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/** Category management. A category with products cannot be deleted: deactivate it instead. */
class CategoryController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Category::class);

        return view('admin.categories.index', ['categories' => Category::withCount('products')->orderBy('name')->get()]);
    }

    public function create(): View
    {
        Gate::authorize('create', Category::class);

        return view('admin.categories.form', ['category' => new Category(['is_active' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Category::class);

        $data = $this->validated($request);
        $data['slug'] = $this->uniqueSlug($data['name']);
        Category::create($data);

        return redirect()->route('admin.categories.index')->with('notice', 'Categoría creada.');
    }

    public function edit(Category $category): View
    {
        Gate::authorize('update', $category);

        return view('admin.categories.form', ['category' => $category]);
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        Gate::authorize('update', $category);

        $category->update($this->validated($request));

        return redirect()->route('admin.categories.index')->with('notice', 'Cambios guardados.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        Gate::authorize('delete', $category);

        if ($category->products()->exists() || $category->children()->exists()) {
            return back()->with('error', 'Esta categoría tiene productos: desactívala en lugar de eliminarla.');
        }

        $category->delete();

        return back()->with('notice', 'Categoría eliminada.');
    }

    /** @return array{name: string, description: ?string, is_active: bool} */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'categoria';
        $slug = $base;

        for ($i = 2; Category::where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }
}
