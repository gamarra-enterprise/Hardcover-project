@php
    $book = $product->bookDetail;
    $selected = old('categories', $product->exists ? $product->categories->pluck('id')->all() : []);
    $v = fn (string $key, $default = null) => old($key, $default);
@endphp

<x-panel-layout :title="$product->exists ? $product->name : 'Nuevo producto'" :subtitle="$product->exists ? 'SKU '.$product->sku : 'Completa los datos del producto.'">
    <p style="margin-top: -10px"><a href="{{ route('admin.products.index') }}">← Volver a productos</a>
        @if ($product->exists && $product->isVisible()) · <a href="{{ route('products.show', $product) }}" target="_blank" rel="noopener">Ver en la tienda</a>@endif</p>

    @if (session('notice'))
        <div class="p-notice" role="status">{{ session('notice') }}</div>
    @endif
    @if ($errors->any())
        <div class="p-notice error" role="alert">Revisa los campos marcados.</div>
    @endif

    <form method="POST" enctype="multipart/form-data"
          action="{{ $product->exists ? route('admin.products.update', $product) : route('admin.products.store') }}">
        @csrf
        @if ($product->exists) @method('PUT') @endif

        <section class="p-card">
            <h2>General</h2>
            <div class="p-grid">
                <label class="p-field">Nombre
                    <input type="text" name="name" value="{{ $v('name', $product->name) }}" required maxlength="255">
                    @error('name')<span class="p-muted" style="color: var(--bad)">{{ $message }}</span>@enderror
                </label>
                <label class="p-field">SKU
                    <input type="text" name="sku" value="{{ $v('sku', $product->sku) }}" required maxlength="64">
                    @error('sku')<span class="p-muted" style="color: var(--bad)">{{ $message }}</span>@enderror
                </label>
            </div>
            <label class="p-field" style="margin-top: 12px">Descripción
                <textarea name="description" rows="5" maxlength="5000" style="width: 100%">{{ $v('description', $product->description) }}</textarea>
            </label>
            <label class="p-field" style="margin-top: 12px; grid-auto-flow: column; justify-content: start; align-items: center; gap: 8px">
                <input type="checkbox" name="hidden" value="1" @checked($v('hidden', $product->status?->value === 'hidden'))> Oculto (no se muestra en la tienda)
            </label>
        </section>

        <section class="p-card">
            <h2>Precio y stock</h2>
            <div class="p-grid">
                @foreach ([['price', 'Precio (S/, IGV incluido)'], ['sale_price', 'Precio de oferta (opcional)'], ['cost_price', 'Costo (no se muestra al público)']] as [$f, $label])
                    <label class="p-field">{{ $label }}
                        <input type="number" step="0.01" min="0" name="{{ $f }}" value="{{ $v($f, $product->$f) }}" @if ($f === 'price') required @endif>
                        @error($f)<span class="p-muted" style="color: var(--bad)">{{ $message }}</span>@enderror
                    </label>
                @endforeach
                <label class="p-field">Stock
                    <input type="number" min="0" name="stock" value="{{ $v('stock', $product->stock) }}" required>
                    @error('stock')<span class="p-muted" style="color: var(--bad)">{{ $message }}</span>@enderror
                </label>
                <label class="p-field">Peso (gramos)
                    <input type="number" min="0" name="weight_grams" value="{{ $v('weight_grams', $product->weight_grams) }}">
                </label>
            </div>
        </section>

        <section class="p-card">
            <h2>Libro <span class="p-muted">(deja el autor vacío si no es un libro)</span></h2>
            <div class="p-grid">
                @foreach ([['author', 'Autor'], ['isbn_13', 'ISBN-13'], ['publisher', 'Editorial'], ['published_year', 'Año'], ['pages', 'Páginas'], ['format', 'Formato']] as [$f, $label])
                    <label class="p-field">{{ $label }}
                        <input type="text" name="{{ $f }}" value="{{ $v($f, $book?->$f) }}">
                        @error($f)<span class="p-muted" style="color: var(--bad)">{{ $message }}</span>@enderror
                    </label>
                @endforeach
            </div>
        </section>

        <section class="p-card">
            <h2>Categorías</h2>
            <div style="display: flex; flex-wrap: wrap; gap: 8px 18px">
                @foreach ($categories as $category)
                    <label><input type="checkbox" name="categories[]" value="{{ $category->id }}" @checked(in_array($category->id, $selected))> {{ $category->name }}</label>
                @endforeach
            </div>
        </section>

        <section class="p-card">
            <h2>Portada</h2>
            @if ($product->imageUrl())
                <img src="{{ $product->imageUrl() }}" alt="Portada actual" style="height: 120px; border-radius: 6px; margin-bottom: 10px">
            @endif
            <input type="file" name="image" accept="image/*">
            @error('image')<p class="p-muted" style="color: var(--bad)">{{ $message }}</p>@enderror
        </section>

        <button class="p-btn p-btn-primary">Guardar</button>
    </form>
</x-panel-layout>
