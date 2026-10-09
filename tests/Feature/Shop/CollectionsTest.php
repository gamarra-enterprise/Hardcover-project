<?php

namespace Tests\Feature\Shop;

use App\Enums\OrderStatus;
use App\Enums\ProductType;
use App\Livewire\Shop\ProductList;
use App\Models\BookDetail;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CollectionsTest extends TestCase
{
    use RefreshDatabase;

    private function book(string $name, array $over = []): Product
    {
        $p = Product::factory()->create(['name' => $name, 'type' => ProductType::BOOK, 'stock' => 5] + $over);
        BookDetail::factory()->create(['product_id' => $p->id]);

        return $p;
    }

    public function test_offers_show_only_discounted_products(): void
    {
        $this->book('Con oferta', ['price' => 50, 'sale_price' => 40]);
        $this->book('Sin oferta');

        $this->get('/ofertas')->assertOk()->assertSee('Ofertas')->assertSee('Con oferta')->assertDontSee('Sin oferta');
    }

    public function test_gifts_leave_the_books_out(): void
    {
        $this->book('Un libro');
        Product::factory()->create(['name' => 'Un llavero', 'type' => ProductType::ACCESSORY, 'stock' => 5]);

        $this->get('/regalos')->assertOk()->assertSee('Un llavero')->assertDontSee('Un libro');
    }

    public function test_best_sellers_are_only_what_was_sold_and_the_most_sold_comes_first(): void
    {
        $few = $this->book('Poco vendido');
        $many = $this->book('Muy vendido');
        $this->book('Nunca vendido');

        foreach ([[$few, 1], [$many, 3]] as [$product, $times]) {
            for ($i = 0; $i < $times; $i++) {
                Order::factory()->guest()->status(OrderStatus::CONFIRMED)->withItems([$product])->create();
            }
        }
        Order::factory()->guest()->status(OrderStatus::CANCELLED)->withItems([$this->book('Cancelado')])->create();

        $this->get('/mas-vendidos')->assertOk()
            ->assertSeeInOrder(['Muy vendido', 'Poco vendido'])
            ->assertDontSee('Nunca vendido')->assertDontSee('Cancelado');
    }

    public function test_new_arrivals_sort_by_date_and_unknown_collections_are_404(): void
    {
        $this->book('Antiguo', ['created_at' => now()->subYear()]);
        $this->book('Reciente');

        $this->get('/novedades')->assertOk()->assertSeeInOrder(['Reciente', 'Antiguo']);
        $this->assertSame('new', Livewire::test(ProductList::class, ['collection' => 'novedades'])->get('sort'));
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        (new ProductList)->mount('inventada');
    }

    public function test_type_filter_counts_and_narrows(): void
    {
        $this->book('Libro uno');
        Product::factory()->create(['name' => 'Figura uno', 'type' => ProductType::FIGURE, 'stock' => 5]);

        $component = Livewire::test(ProductList::class);
        $this->assertSame(['' => 2, 'libro' => 1, 'figura' => 1], $component->instance()->typeCounts());

        $component->set('type', 'figura')->assertSee('Figura uno')->assertDontSee('Libro uno');
    }
}
