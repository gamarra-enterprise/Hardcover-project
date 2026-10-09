@use('App\Enums\ProductStatus')
@use('App\Support\Money')

<x-panel-layout title="Productos" subtitle="Libros y productos afines del catálogo.">
    @if (session('notice'))
        <div class="p-notice" role="status">{{ session('notice') }}</div>
    @endif

    <form method="GET" class="p-tools">
        <label class="p-field">Buscar
            <input type="search" name="q" value="{{ $search }}" placeholder="Título, autor, SKU o ISBN">
        </label>
        <label class="p-field">Estado
            <select name="estado">
                <option value="">Todos</option>
                @foreach (ProductStatus::cases() as $s)
                    <option value="{{ $s->value }}" @selected($status === $s)>{{ $s->label() }}</option>
                @endforeach
            </select>
        </label>
        <button class="p-btn p-btn-primary">Filtrar</button>
        @if ($search !== '' || $status)
            <a class="p-btn" href="{{ route('admin.products.index') }}">Limpiar</a>
        @endif
        <a class="p-btn" href="{{ route('admin.products.create') }}" style="margin-left: auto">Nuevo producto</a>
    </form>

    <div class="p-table-wrap">
        <table class="p-table">
            <thead>
                <tr><th>Producto</th><th>SKU</th><th>Estado</th><th class="num">Stock</th><th class="num">Precio</th></tr>
            </thead>
            <tbody>
                @forelse ($products as $product)
                    <tr>
                        <td>
                            <a href="{{ route('admin.products.edit', $product) }}"><b>{{ $product->name }}</b></a>
                            @if ($product->bookDetail)<br><span class="p-muted">{{ $product->bookDetail->author }}</span>@endif
                        </td>
                        <td>{{ $product->sku }}</td>
                        <td><span class="p-pill {{ $product->status === ProductStatus::HIDDEN ? 'off' : ($product->status === ProductStatus::OUT_OF_STOCK ? 'warn' : 'done') }}">{{ $product->status->label() }}</span></td>
                        <td class="num">{{ $product->stock }}</td>
                        <td class="num">{{ Money::format($product->currentPrice()) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="p-muted" style="text-align: center; padding: 28px">No hay productos con esos filtros.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top: 16px">{{ $products->links() }}</div>
</x-panel-layout>
