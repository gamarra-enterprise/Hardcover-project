<?php

namespace Tests\Feature\Admin;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_staff_use_the_inventory(): void
    {
        $product = Product::factory()->create();

        $this->actingAs(User::factory()->create())->get(route('admin.inventory.index'))->assertForbidden();
        $this->actingAs(User::factory()->create())->patch(route('admin.inventory.update', $product), ['stock' => 99])->assertForbidden();
    }

    public function test_stock_changes_status_with_it_and_is_recorded(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create(['stock' => 4]);

        $this->actingAs($admin)->patch(route('admin.inventory.update', $product), ['stock' => 0])->assertSessionHas('notice');
        $this->assertSame(ProductStatus::OUT_OF_STOCK, $product->fresh()->status);
        $this->assertDatabaseHas('activity_logs', ['action' => 'product.stock_changed']);

        $this->actingAs($admin)->patch(route('admin.inventory.update', $product), ['stock' => 12]);
        $this->assertSame(ProductStatus::ACTIVE, $product->fresh()->status);
        $this->actingAs($admin)->patch(route('admin.inventory.update', $product), ['stock' => -1])->assertSessionHasErrors('stock');
    }

    public function test_a_hidden_product_stays_hidden_when_restocked(): void
    {
        $product = Product::factory()->hidden()->create(['stock' => 0]);

        $this->actingAs(User::factory()->admin()->create())->patch(route('admin.inventory.update', $product), ['stock' => 8]);

        $this->assertSame(ProductStatus::HIDDEN, $product->fresh()->status);
    }

    public function test_low_stock_filter_and_the_dashboard_list_it(): void
    {
        Product::factory()->create(['name' => 'Casi agotado', 'stock' => 2]);
        Product::factory()->create(['name' => 'Bien surtido', 'stock' => 40]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.inventory.index', ['bajo' => 1]))->assertSee('Casi agotado')->assertDontSee('Bien surtido');
        $this->actingAs($admin)->get(route('admin.index'))->assertOk()->assertSee('Reponer pronto')->assertSee('Casi agotado')->assertSee('Ticket promedio');
    }
}
