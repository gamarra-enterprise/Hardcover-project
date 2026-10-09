<?php

namespace Tests\Feature\Admin;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminCatalogTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function payload(array $over = []): array
    {
        return array_merge(['type' => 'libro', 'sku' => 'LIB-001', 'name' => 'El Aleph', 'price' => '45.00', 'stock' => 5], $over);
    }

    public function test_customer_cannot_manage_the_catalog(): void
    {
        $customer = User::factory()->create();
        $product = Product::factory()->create();

        $this->actingAs($customer)->get(route('admin.products.index'))->assertForbidden();
        $this->actingAs($customer)->post(route('admin.products.store'), $this->payload())->assertForbidden();
        $this->actingAs($customer)->get(route('admin.categories.index'))->assertForbidden();
        $this->actingAs($customer)->put(route('admin.products.update', $product), $this->payload())->assertForbidden();
    }

    public function test_admin_creates_a_book_with_categories_and_cover(): void
    {
        Storage::fake('public');
        $category = Category::factory()->create();

        $this->actingAs($this->admin())->post(route('admin.products.store'), $this->payload([
            'author' => 'Jorge Luis Borges',
            'isbn_13' => '9788420633121',
            'categories' => [$category->id],
            'image' => UploadedFile::fake()->image('portada.jpg'),
        ]))->assertRedirect();

        $product = Product::where('sku', 'LIB-001')->firstOrFail();
        $this->assertSame('el-aleph', $product->slug);
        $this->assertSame(ProductStatus::ACTIVE, $product->status);
        $this->assertSame('Jorge Luis Borges', $product->bookDetail->author);
        $this->assertTrue($product->categories->contains($category));
        Storage::disk('public')->assertExists($product->image_path);
    }

    public function test_repeated_names_get_distinct_slugs(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.products.store'), $this->payload());
        $this->actingAs($admin)->post(route('admin.products.store'), $this->payload(['sku' => 'LIB-002']));

        $this->assertEqualsCanonicalizing(['el-aleph', 'el-aleph-2'], Product::pluck('slug')->all());
    }

    public function test_validation_rejects_duplicate_sku_and_sale_price_above_price(): void
    {
        Product::factory()->create(['sku' => 'LIB-001']);

        $this->actingAs($this->admin())->post(route('admin.products.store'), $this->payload(['sale_price' => '60']))
            ->assertSessionHasErrors(['sku', 'sale_price']);
    }

    public function test_update_keeps_the_slug_and_hiding_works_both_ways(): void
    {
        $admin = $this->admin();
        $product = Product::factory()->create(['slug' => 'viejo', 'stock' => 4]);

        $this->actingAs($admin)->put(route('admin.products.update', $product), $this->payload(['sku' => $product->sku, 'name' => 'Nuevo nombre', 'stock' => 4, 'hidden' => 1]))
            ->assertRedirect();
        $product->refresh();
        $this->assertSame('viejo', $product->slug);
        $this->assertSame(ProductStatus::HIDDEN, $product->status);

        $this->actingAs($admin)->put(route('admin.products.update', $product), $this->payload(['sku' => $product->sku, 'stock' => 0]));
        $this->assertSame(ProductStatus::OUT_OF_STOCK, $product->fresh()->status);
    }

    public function test_replacing_the_cover_deletes_the_old_file(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.products.store'), $this->payload(['image' => UploadedFile::fake()->image('a.jpg')]));
        $product = Product::firstOrFail();
        $old = $product->image_path;

        $this->actingAs($admin)->put(route('admin.products.update', $product), $this->payload(['image' => UploadedFile::fake()->image('b.jpg')]));

        Storage::disk('public')->assertMissing($old);
        Storage::disk('public')->assertExists($product->fresh()->image_path);
    }

    public function test_products_list_searches_by_name(): void
    {
        Product::factory()->create(['name' => 'Rayuela']);
        Product::factory()->create(['name' => 'Ficciones']);

        $this->actingAs($this->admin())->get(route('admin.products.index', ['q' => 'rayu']))
            ->assertSee('Rayuela')->assertDontSee('Ficciones');
    }

    public function test_category_with_products_cannot_be_deleted_but_an_empty_one_can(): void
    {
        $admin = $this->admin();
        $used = Category::factory()->create();
        $used->products()->attach(Product::factory()->create());
        $empty = Category::factory()->create();

        $this->actingAs($admin)->delete(route('admin.categories.destroy', $used))->assertSessionHas('error');
        $this->assertModelExists($used);

        $this->actingAs($admin)->delete(route('admin.categories.destroy', $empty));
        $this->assertModelMissing($empty);
    }

    public function test_admin_creates_and_deactivates_a_category(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.categories.store'), ['name' => 'Poesía', 'is_active' => 1])->assertRedirect();
        $category = Category::where('slug', 'poesia')->firstOrFail();
        $this->assertTrue($category->is_active);

        $this->actingAs($admin)->put(route('admin.categories.update', $category), ['name' => 'Poesía']);
        $this->assertFalse($category->fresh()->is_active);
    }
}
