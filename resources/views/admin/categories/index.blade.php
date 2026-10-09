<x-panel-layout title="Categorías" subtitle="Las ★ principales salen como filtros y en el menú; las demás aparecen en «Más géneros».">
    @if (session('notice'))<div class="p-notice" role="status">{{ session('notice') }}</div>@endif
    @if (session('error'))<div class="p-notice error" role="alert">{{ session('error') }}</div>@endif

    <p><a class="p-btn" href="{{ route('admin.categories.create') }}">Nueva categoría</a></p>

    <div class="p-table-wrap">
        <table class="p-table">
            <thead><tr><th>Nombre</th><th>Estado</th><th>En los filtros</th><th class="num">Productos</th><th></th></tr></thead>
            <tbody>
                @foreach ($categories as $category)
                    <tr>
                        <td><a href="{{ route('admin.categories.edit', $category) }}"><b>{{ $category->name }}</b></a></td>
                        <td><span class="p-pill {{ $category->is_active ? 'done' : 'off' }}">{{ $category->is_active ? 'Activa' : 'Inactiva' }}</span></td>
                        <td>
                            <form method="POST" action="{{ route('admin.categories.featured', $category) }}">
                                @csrf @method('PATCH')
                                <button class="p-btn" title="{{ $category->featured ? 'Pasar a «Más géneros»' : 'Mostrar entre los filtros principales' }}">{{ $category->featured ? '★ Principal' : '☆ En «Más»' }}</button>
                            </form>
                        </td>
                        <td class="num">{{ $category->products_count }}</td>
                        <td>
                            @if ($category->products_count === 0)
                                <form method="POST" action="{{ route('admin.categories.destroy', $category) }}" onsubmit="return confirm('¿Eliminar esta categoría?')">
                                    @csrf @method('DELETE')
                                    <button class="p-btn">Eliminar</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-panel-layout>
