<?php

namespace App\Livewire\Shop;

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

    /** Any change in the filters starts again from the first page. */
    public function updated(string $property): void
    {
        if ($property !== 'page') {
            $this->resetPage();
        }
    }

    public function clear(string $filter): void
    {
        match ($filter) {
            'search' => $this->search = '',
            'category' => $this->category = '',
            'maxPrice' => $this->maxPrice = null,
            'onSale' => $this->onSale = false,
            'inStock' => $this->inStock = false,
            default => null,
        };
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'category', 'maxPrice', 'onSale', 'inStock', 'sort');
        $this->resetPage();
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

        match ($this->sort) {
            'new' => $query->latest()->orderBy('id'),
            'asc' => $query->orderByRaw('coalesce(sale_price, price) asc')->orderBy('name'),
            'desc' => $query->orderByRaw('coalesce(sale_price, price) desc')->orderBy('name'),
            'az' => $query->orderByRaw('unaccent(lower(name))'),
            default => $query->orderByRaw("case when status = 'out_of_stock' then 1 else 0 end")->orderByRaw('unaccent(lower(name))'),
        };

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
    private function applyFilters(Builder $query, bool $withCategory = true): Builder
    {
        $query->visible();

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
        if ($this->maxPrice !== null && $this->maxPrice < $this->priceCeiling) {
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
        return view('livewire.shop.product-list');
    }
}
