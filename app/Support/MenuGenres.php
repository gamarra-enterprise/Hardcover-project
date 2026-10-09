<?php

namespace App\Support;

use App\Models\Category;
use Illuminate\Support\Facades\Cache;

/** The genres that have products on the shelf, most stocked first, for the menu and the home. */
class MenuGenres
{
    /** @return list<array{name: string, slug: string, count: int}> */
    public static function all(int $limit = 8): array
    {
        return Cache::remember("menu-genres-{$limit}", 300, fn () => Category::query()
            ->where('is_active', true)
            ->withCount(['products as shown_count' => fn ($q) => $q->visible()])
            ->get()
            ->filter(fn (Category $c) => $c->shown_count > 0)
            ->sortByDesc('shown_count')
            ->take($limit)
            ->map(fn (Category $c) => ['name' => $c->name, 'slug' => $c->slug, 'count' => $c->shown_count])
            ->values()
            ->all());
    }
}
