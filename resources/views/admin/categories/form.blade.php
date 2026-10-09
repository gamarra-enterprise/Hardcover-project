<x-panel-layout :title="$category->exists ? $category->name : 'Nueva categoría'">
    <p style="margin-top: -10px"><a href="{{ route('admin.categories.index') }}">← Volver a categorías</a></p>

    <form method="POST" class="p-card" action="{{ $category->exists ? route('admin.categories.update', $category) : route('admin.categories.store') }}">
        @csrf
        @if ($category->exists) @method('PUT') @endif
        <label class="p-field">Nombre
            <input type="text" name="name" value="{{ old('name', $category->name) }}" required maxlength="120">
            @error('name')<span class="p-muted" style="color: var(--bad)">{{ $message }}</span>@enderror
        </label>
        <label class="p-field" style="margin-top: 12px">Descripción
            <textarea name="description" rows="3" maxlength="500" style="width: 100%">{{ old('description', $category->description) }}</textarea>
        </label>
        <label style="display: block; margin: 12px 0">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $category->is_active))> Activa (visible en la tienda)
        </label>
        <button class="p-btn p-btn-primary">Guardar</button>
    </form>
</x-panel-layout>
