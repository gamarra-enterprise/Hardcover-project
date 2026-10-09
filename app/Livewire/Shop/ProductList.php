<?php

namespace App\Livewire\Shop;

use App\Enums\ProductType;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.shop-layout')]
#[Title('Catálogo')]
class ProductList extends Component
{
    use WithPagination;

    public const PER_PAGE = 12;

    public const SORTS = [
        'rel' => 'Recomendados',
        'new' => 'Novedades',
        'asc' => 'Precio: menor a mayor',
        'desc' => 'Precio: mayor a menor',
        'az' => 'Título A-Z',
    ];

    /** Preset listing: novedades, masvendidos, ofertas or regalos. Null is the whole catalog. */
    public ?string $collection = null;

    #[Url(as: 'tipo', except: '')]
    public string $type = '';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'genero', except: '')]
    public string $category = '';

    #[Url(as: 'precio', except: null)]
    public ?int $maxPrice = null;

    #[Url(as: 'oferta', except: false)]
    public bool $onSale = false;

    #[Url(as: 'stock', except: false)]
    public bool $inStock = false;

    #[Url(as: 'orden', except: 'rel')]
    public string $sort = 'rel';

    public const COLLECTIONS = [
        'novedades' => ['Novedades', 'Lo último que llegó a la librería.'],
        'masvendidos' => ['Más vendidos', 'Lo que más se llevó en los últimos 90 días.'],
        'ofertas' => ['Ofertas', 'Productos con precio rebajado.'],
        'regalos' => ['Papelería y regalos', 'Separadores, llaveros, figuras y más.'],
    ];

    public function mount(?string $collection = null): void
    {
        abort_if($collection !== null && ! isset(self::COLLECTIONS[$collection]), 404);

        $this->collection = $collection;

        if ($collection === 'novedades' && $this->sort === 'rel') {
            $this->sort = 'new';
        }
    }

    public function heading(): string
    {
        return $this->collection ? self::COLLECTIONS[$this->collection][0] : 'Catálogo';
    }

    public function subheading(): ?string
    {
        return $this->collection ? self::COLLECTIONS[$this->collection][1] : null;
    }

    /** @return array<string, string> product types with a label, for the type selector */
    public function typeOptions(): array
    {
        return collect(ProductType::cases())->mapWithKeys(fn (ProductType $t) => [$t->value => $t->label()])->all();
    }

    /** @return array<string, int> how many products each type would show with the other filters, plus '' for all */
    #[Computed]
    public function typeCounts(): array
    {
        $counts = $this->applyFilters(Product::query(), withCategory: true, withType: false)
            ->selectRaw('products.type, count(*) as n')->groupBy('products.type')->pluck('n', 'type')->all();

        return ['' => array_sum($counts)] + $counts;
    }

    /**
     * Six equal price bands up to the ceiling, with how many products fall in each (the other filters applied),
     * for the little histogram above the price slider.
     *
     * @return list<int>
     */
    #[Computed]
    public function priceBins(): array
    {
        $step = max(1, $this->priceCeiling / 6);
        $bins = array_fill(0, 6, 0);

        $this->applyFilters(Product::query(), withPrice: false)
            ->selectRaw('least(5, floor(coalesce(products.sale_price, products.price) / ?)) as band, count(*) as n', [$step])
            ->groupBy('band')->pluck('n', 'band')
            ->each(function ($n, $band) use (&$bins) {
                $bins[(int) $band] = (int) $n;
            });

        return $bins;
    }

    /** Any change in the filters starts again from the first page. */
    public function updated(string $property): void
    {
        if ($property !== 'page' && ! str_starts_with($property, 'paginators')) {
            $this->resetPage();
        }

        $this->dispatch('listing-changed');
    }

    public function clear(string $filter): void
    {
        match ($filter) {
            'search' => $this->search = '',
            'category' => $this->category = '',
            'type' => $this->type = '',
            'maxPrice' => $this->maxPrice = null,
            'onSale' => $this->onSale = false,
            'inStock' => $this->inStock = false,
            default => null,
        };
        $this->resetPage();
        $this->dispatch('listing-changed');
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'type', 'category', 'maxPrice', 'onSale', 'inStock', 'sort');
        $this->resetPage();
        $this->dispatch('listing-changed');
    }

    /** Highest price in the catalog, rounded up to a multiple of 5, for the price slider. */
    #[Computed]
    public function priceCeiling(): int
    {
        $max = Product::visible()->selectRaw('max(coalesce(sale_price, price)) as top')->value('top');

        return max(5, (int) (ceil((float) $max / 5) * 5));
    }

    /** @return Collection<int, Category> categories with how many products each would show */
    #[Computed]
    public function categories(): Collection
    {
        return Category::query()
            ->where('is_active', true)
            ->withCount(['products as visible_count' => fn (Builder $q) => $this->applyFilters($q, withCategory: false)])
            ->get()
            ->filter(fn (Category $c) => $c->visible_count > 0)
            ->sortByDesc('visible_count')
            ->values();
    }

    #[Computed]
    public function total(): int
    {
        return $this->applyFilters(Product::query(), withCategory: false)->count();
    }

    #[Computed]
    public function products(): LengthAwarePaginator
    {
        $query = $this->applyFilters(Product::query()->with(['bookDetail', 'categories']));

        if ($this->collection === 'masvendidos' && $this->sort === 'rel') {
            $query->reorder()->orderByRaw(Product::SOLD_UNITS_SQL.' desc')->orderBy('id');
        } else {
        match ($this->sort) {
            'new' => $query->latest()->orderBy('id'),
            'asc' => $query->orderByRaw('coalesce(sale_price, price) asc')->orderBy('name'),
            'desc' => $query->orderByRaw('coalesce(sale_price, price) desc')->orderBy('name'),
            'az' => $query->orderByRaw('unaccent(lower(name))'),
            default => $query->orderByRaw("case when status = 'out_of_stock' then 1 else 0 end")->orderByRaw('unaccent(lower(name))'),
        };
        }

        return $query->paginate(self::PER_PAGE);
    }

    /** Labels of the filters in use, to show them as removable chips. */
    #[Computed]
    public function activeFilters(): array
    {
        $chips = [];

        if (trim($this->search) !== '') {
            $chips['search'] = '«'.trim($this->search).'»';
        }
        if ($this->type !== '' && ($t = ProductType::tryFrom($this->type))) {
            $chips['type'] = $t->label();
        }
        if ($this->category !== '' && ($name = Category::where('slug', $this->category)->value('name'))) {
            $chips['category'] = $name;
        }
        if ($this->maxPrice !== null && $this->maxPrice < $this->priceCeiling) {
            $chips['maxPrice'] = 'Hasta S/ '.$this->maxPrice;
        }
        if ($this->onSale) {
            $chips['onSale'] = 'En oferta';
        }
        if ($this->inStock) {
            $chips['inStock'] = 'Con stock';
        }

        return $chips;
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    private function applyFilters(Builder $query, bool $withCategory = true, bool $withType = true, bool $withPrice = true): Builder
    {
        $query->visible();

        match ($this->collection) {
            'masvendidos' => $query->whereRaw(Product::SOLD_UNITS_SQL.' > 0'),
            'ofertas' => $query->whereNotNull('products.sale_price'),
            'regalos' => $query->where('products.type', '!=', ProductType::BOOK->value),
            default => null,
        };

        if ($withType && $this->type !== '' && ProductType::tryFrom($this->type)) {
            $query->where('products.type', $this->type);
        }

        $term = trim($this->search);
        if ($term !== '') {
            $like = '%'.addcslashes($term, '\\%_').'%';
            $digits = preg_replace('/\D/', '', $term);

            $query->where(function (Builder $q) use ($like, $digits) {
                $q->whereRaw('unaccent(products.name) ilike unaccent(?)', [$like])
                    ->orWhere('products.sku', 'ilike', $like)
                    ->orWhereHas('bookDetail', function (Builder $b) use ($like, $digits) {
                        $b->whereRaw('unaccent(author) ilike unaccent(?)', [$like])
                            ->orWhereRaw('unaccent(publisher) ilike unaccent(?)', [$like])
                            ->orWhereRaw('unaccent(genres) ilike unaccent(?)', [$like])
                            ->when(strlen($digits) >= 4, fn (Builder $i) => $i->orWhere('isbn_13', 'like', $digits.'%'));
                    });
            });
        }

        if ($withCategory && $this->category !== '') {
            $query->whereHas('categories', fn (Builder $c) => $c->where('categories.slug', $this->category));
        }
        if ($withPrice && $this->maxPrice !== null && $this->maxPrice < $this->priceCeiling) {
            $query->whereRaw('coalesce(products.sale_price, products.price) <= ?', [$this->maxPrice]);
        }
        if ($this->onSale) {
            $query->whereNotNull('products.sale_price');
        }
        if ($this->inStock) {
            $query->where('products.stock', '>', 0);
        }

        return $query;
    }

    public function render()
    {
        return view('livewire.shop.product-list')->title($this->heading());
    }
}
