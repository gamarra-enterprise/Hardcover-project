<?php

namespace Tests\Feature\Admin;

use App\Models\BookDetail;
use App\Models\Product;
use App\Models\User;
use App\Services\ProductImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogExportTest extends TestCase
{
    use RefreshDatabase;

    private function book(array $product = [], array $detail = []): Product
    {
        $p = Product::factory()->create($product);
        BookDetail::factory()->create(['product_id' => $p->id] + $detail + ['isbn_13' => '9788420633121', 'isbn_10' => null]);

        return $p;
    }

    public function test_customer_cannot_export(): void
    {
        $this->actingAs(User::factory()->create())->get(route('admin.products.export'))->assertForbidden();
    }

    public function test_export_lists_books_and_guards_against_formulas(): void
    {
        $this->book(['name' => '=HYPERLINK("x")', 'price' => '45.00', 'cost_price' => '20.00', 'stock' => 3, 'height_mm' => 215, 'width_mm' => 140], ['author' => 'Borges']);
        Product::factory()->create(['name' => 'Sin libro']);

        $response = $this->actingAs(User::factory()->admin()->create())->get(route('admin.products.export'));
        $csv = $response->streamedContent();

        $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('ISBN,TÍTULO,AUTOR', $csv);
        $this->assertStringContainsString('9788420633121', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringContainsString('21.5 x 14', $csv);
        $this->assertStringNotContainsString('Sin libro', $csv);
    }

    public function test_exported_file_can_be_imported_back_without_skips(): void
    {
        $this->book(['name' => 'El Aleph', 'height_mm' => 215, 'width_mm' => 140], ['author' => 'Borges', 'genres' => 'cuento']);
        $csv = $this->actingAs(User::factory()->admin()->create())->get(route('admin.products.export'))->streamedContent();
        $path = tempnam(sys_get_temp_dir(), 'cat');
        file_put_contents($path, $csv);

        $result = (new ProductImporter)->import($path, dryRun: true);

        $this->assertSame([], $result['skipped']);
        $this->assertSame(1, $result['created'] + $result['updated']);
        unlink($path);
    }
}
