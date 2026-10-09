<?php

namespace Tests\Feature\Performance;

use App\Models\BookDetail;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Guards against N+1 queries on the pages most visitors hit: the count must not grow with the catalog. */
class QueryCountTest extends TestCase
{
    use RefreshDatabase;

    private function queries(string $url): int
    {
        $n = 0;
        DB::listen(function () use (&$n) {
            $n++;
        });
        $this->get($url)->assertOk();

        return $n;
    }

    private function seedCatalog(int $products): void
    {
        $category = Category::factory()->create();
        Product::factory()->count($products)->create(['stock' => 5])->each(function (Product $p) use ($category) {
            BookDetail::factory()->create(['product_id' => $p->id]);
            $p->categories()->attach($category);
        });
    }

    public function test_catalog_and_home_do_not_query_per_product(): void
    {
        $this->seedCatalog(3);
        $small = [$this->queries('/'), $this->queries('/catalogo')];

        $this->seedCatalog(12);
        $big = [$this->queries('/'), $this->queries('/catalogo')];

        $this->assertSame($small, $big, 'home / catalog queries grew with the number of products: '.json_encode([$small, $big]));
    }
}
