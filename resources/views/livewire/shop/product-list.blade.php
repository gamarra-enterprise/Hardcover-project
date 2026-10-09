<div class="wrap" style="padding-block: 28px 0">
    <nav class="muted" style="font-size: 13px" aria-label="Ruta"><a href="{{ route('home') }}">Inicio</a> / {{ $this->heading() }}</nav>
    <h1 style="font-size: clamp(2.2rem, 5vw, 3.4rem); font-weight: 800; letter-spacing: -.035em; margin-top: .3rem">{{ $this->heading() }}</h1>
    @if ($this->subheading())<p class="muted" style="margin-top: .3rem">{{ $this->subheading() }}</p>@endif

    <div style="margin-top: 22px; display: grid; gap: 14px" x-data="{ open: false }">
        @if (! in_array($collection, ['regalos'], true))
            <div class="scroll-x">
                <div class="seg2" role="group" aria-label="Tipo de producto">
                    <button type="button" wire:click="$set('type', '')" aria-pressed="{{ $type === '' ? 'true' : 'false' }}">Todo<span class="cnt">{{ $this->typeCounts[''] }}</span></button>
                    @foreach ($this->typeOptions() as $value => $label)
                        @if (($this->typeCounts[$value] ?? 0) > 0 || $type === $value)
                            <button type="button" wire:key="type-{{ $value }}" wire:click="$set('type', '{{ $value }}')" aria-pressed="{{ $type === $value ? 'true' : 'false' }}">{{ $label }}<span class="cnt">{{ $this->typeCounts[$value] ?? 0 }}</span></button>
                        @endif
                    @endforeach
                </div>
            </div>
        @endif
        @if ($this->categories->isNotEmpty())
            <div class="chips-row" role="group" aria-label="Género">
                <button type="button" class="chip" wire:click="$set('category', '')" aria-pressed="{{ $category === '' ? 'true' : 'false' }}">
                    Todos<span class="cnt">{{ $this->total }}</span>
                </button>
                @foreach ($this->categories as $item)
                    <button type="button" class="chip" wire:key="cat-{{ $item->id }}" wire:click="$set('category', '{{ $item->slug }}')" aria-pressed="{{ $category === $item->slug ? 'true' : 'false' }}">
                        {{ $item->name }}<span class="cnt">{{ $item->visible_count }}</span>
                    </button>
                @endforeach
            </div>
        @endif

        <div style="display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap">
            <button type="button" class="btn btn-sm" style="border-color: var(--line)" x-on:click="open = !open" x-bind:aria-expanded="open" aria-controls="filter-panel">
                <span x-text="open ? 'Menos filtros ▴' : 'Más filtros ▾'">Más filtros ▾</span>
            </button>
            <div style="display: flex; align-items: center; gap: .6rem">
                <label for="sort" class="label">Ordenar</label>
                <select id="sort" class="select" wire:model.live="sort">
                    @foreach (\App\Livewire\Shop\ProductList::SORTS as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div id="filter-panel" class="fpanel" x-show="open" x-cloak x-transition>
            <div class="fpanel-in">
                <div>
                    <div class="label" style="display: flex; justify-content: space-between">
                        Precio máximo <span class="num" style="color: var(--ink)">S/ {{ $maxPrice ?? $this->priceCeiling }}</span>
                    </div>
                    @php
                        $bins = $this->priceBins;
                        $top = max(1, ...$bins);
                        $step = $this->priceCeiling / 6;
                        $limit = $maxPrice ?? $this->priceCeiling;
                    @endphp
                    <div class="hist" aria-hidden="true">
                        @foreach ($bins as $i => $n)
                            <i class="{{ $i * $step < $limit ? 'on' : '' }}" style="height: {{ max(9, $n / $top * 100) }}%" title="S/ {{ round($i * $step) }} a {{ round(($i + 1) * $step) }}: {{ $n }}"></i>
                        @endforeach
                    </div>
                    <label for="max-price" class="sr-only-label">Precio máximo</label>
                    <input id="max-price" type="range" min="5" max="{{ $this->priceCeiling }}" step="5" value="{{ $maxPrice ?? $this->priceCeiling }}"
                           wire:model.live.debounce.300ms="maxPrice" style="width: 100%; accent-color: var(--ink); margin-top: .6rem">
                </div>
                <label class="switch-row">Solo en oferta
                    <span class="switch"><input type="checkbox" wire:model.live="onSale"><span></span></span>
                </label>
                <label class="switch-row">Solo con stock
                    <span class="switch"><input type="checkbox" wire:model.live="inStock"><span></span></span>
                </label>
            </div>
        </div>
    </div>

    <div class="achips">
        @forelse ($this->activeFilters as $key => $label)
            <button type="button" class="achip" wire:key="chip-{{ $key }}" wire:click="clear('{{ $key }}')" aria-label="Quitar filtro {{ $label }}">{{ $label }} ✕</button>
        @empty
            <span class="muted" style="font-size: 13.5px">Sin filtros activos</span>
        @endforelse
        @if ($this->activeFilters)
            <button type="button" class="tlink" wire:click="resetFilters" style="margin-left: .4rem">Limpiar todo</button>
        @endif
    </div>

    <div style="margin-top: 18px" wire:loading.class="loading">
        <div class="muted" style="margin-bottom: 14px" aria-live="polite">
            <b style="color: var(--ink)">{{ $this->products->total() }}</b> {{ $this->products->total() === 1 ? 'producto' : 'productos' }}
        </div>

        @if ($this->products->isEmpty())
            <div class="card bg-surface" style="padding: 32px; text-align: center">
                <b>No encontramos nada con esos filtros.</b>
                <p class="muted">Prueba cambiando la búsqueda o quitando algún filtro.</p>
                <button type="button" class="btn" style="margin-top: .8rem" wire:click="resetFilters">Limpiar filtros</button>
            </div>
        @else
            <div class="pgrid">
                @foreach ($this->products as $i => $product)
                    <x-shop.product-card :product="$product" :index="$i" wire:key="p-{{ $product->id }}" />
                @endforeach
            </div>

            @if ($this->products->hasPages())
                <nav class="pager" aria-label="Paginación">
                    <button type="button" class="btn btn-sm" wire:click="previousPage" @disabled($this->products->onFirstPage())>← Anterior</button>
                    <span class="num muted">Página {{ $this->products->currentPage() }} de {{ $this->products->lastPage() }}</span>
                    <button type="button" class="btn btn-sm" wire:click="nextPage" @disabled(! $this->products->hasMorePages())>Siguiente →</button>
                </nav>
            @endif
        @endif
    </div>
</div>
