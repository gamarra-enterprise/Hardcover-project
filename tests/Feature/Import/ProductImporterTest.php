<?php

namespace Tests\Feature\Import;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use App\Services\ProductImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductImporterTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/hb-import-'.uniqid();
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir.'/*') ?: []);
        rmdir($this->dir);
        parent::tearDown();
    }

    private function csv(): string
    {
        $path = $this->dir.'/inventario.csv';
        file_put_contents($path, <<<'CSV'
HARDCOVER BOOKS,,,,,,,,,,,,,
STOCK,TÍTULO,AUTOR,GÉNERO,EDITORIAL,ISBN,PÁGINAS,TAMAÑO,FORMATO,INFO LIBRO,INFO AUTOR,WEB,PDC,PVP
2,Crónicas marcianas,Ray Bradbury,ficción / novela / contemporanéo,Zorro Rojo,9788412537109,136,24 x 16.5,tapa blanda,"Primera línea.

Segunda línea.",Autor norteamericano.,no,36.3,66
1,Ovnis,Adam Boardman,ficció / novela,Zorro Rojo,978-8412314311,128,"19,5 × 16,5",tapa dura,Una guía ilustrada.,,no,40,"54,5"
1,Repetido,Alguien,ficción,Otra,9788412537109,10,20 x 10,tapa blanda,x,,no,1,1
1,ISBN roto,Alguien,ficción,Otra,123,10,20 x 10,tapa blanda,x,,no,1,1
3,Sin medidas,Otra Autora,novela,Otra,9780000000002,90,grande,tapa blanda,,,no,10,20
CSV);

        return $path;
    }

    public function test_imports_books_with_sku_from_the_isbn(): void
    {
        $result = (new ProductImporter(2))->import($this->csv());

        $this->assertSame(3, $result['created']);
        $product = Product::where('sku', 'HB-9788412537109')->firstOrFail();
        $this->assertSame('Crónicas marcianas', $product->name);
        $this->assertSame('cronicas-marcianas', $product->slug);
        $this->assertSame('66.00', (string) $product->price);
        $this->assertSame('36.30', (string) $product->cost_price);
        $this->assertSame(2, $product->stock);
        $this->assertStringContainsString('Segunda línea.', $product->description);
        $this->assertSame('Ray Bradbury', $product->bookDetail->author);
        $this->assertSame('9788412537109', $product->bookDetail->isbn_13);
        $this->assertSame('Tapa blanda', $product->bookDetail->format);
        $this->assertSame('Autor norteamericano.', $product->bookDetail->author_bio);
        $this->assertSame('HB-9788412314311', Product::where('name', 'Ovnis')->value('sku'));
    }

    public function test_size_is_height_by_width_in_centimeters_and_weight_is_left_empty(): void
    {
        (new ProductImporter(2))->import($this->csv());

        $first = Product::where('sku', 'HB-9788412537109')->first();
        $this->assertSame([240, 165, null, null], [$first->height_mm, $first->width_mm, $first->depth_mm, $first->weight_grams]);

        $comma = Product::where('name', 'Ovnis')->first();
        $this->assertSame([195, 165], [$comma->height_mm, $comma->width_mm]);
        $this->assertSame('54.50', (string) $comma->price);
    }

    public function test_main_genres_become_categories_and_the_rest_stay_as_text(): void
    {
        $result = (new ProductImporter(2))->import($this->csv());

        $this->assertEqualsCanonicalizing(['ficción', 'novela'], $result['filter_genres']);
        $this->assertEqualsCanonicalizing(['Ficción', 'Novela'], Category::pluck('name')->all());

        $book = Product::where('sku', 'HB-9788412537109')->first();
        // The typo "contemporanéo" is fixed and kept in the text, not as a filter.
        $this->assertSame('ficción / novela / contemporáneo', $book->bookDetail->genres);
        $this->assertCount(2, $book->categories);
        // "ficció" in the second row was fixed to "ficción".
        $this->assertSame('ficción / novela', Product::where('name', 'Ovnis')->first()->bookDetail->genres);
    }

    public function test_invalid_rows_are_skipped_with_a_reason_and_bad_sizes_warn(): void
    {
        $result = (new ProductImporter(2))->import($this->csv());

        $this->assertCount(2, $result['skipped']);
        $this->assertStringContainsString('ISBN repetido', $result['skipped'][0]);
        $this->assertStringContainsString('ISBN inválido', $result['skipped'][1]);
        // One for the unreadable size and one for the WEB value "no".
        $this->assertCount(2, $result['warnings']);
        $this->assertNull(Product::where('name', 'Sin medidas')->value('height_mm'));
    }

    public function test_a_blank_author_is_taken_from_the_previous_book(): void
    {
        $lines = [
            'STOCK,TÍTULO,AUTOR,GÉNERO,EDITORIAL,ISBN,PÁGINAS,TAMAÑO,FORMATO,INFO LIBRO,INFO AUTOR,WEB,PDC,PVP',
            '1,Mujeres,Charles Bukowski,novela,Anagrama,9788433920997,344,20 x 13,tapa blanda,x,Poeta de Los Ángeles.,no,41,82',
            '1,Cartero,,novela,Anagrama,9788433920638,192,20 x 13,tapa blanda,x,,no,22,45',
            '1,Otro autor,Alguien Más,novela,Anagrama,9788433921987,336,20 x 13,tapa blanda,x,,no,47,86',
            '1,Sin dato,,novela,Anagrama,9788433914699,138,20 x 13,tapa blanda,x,,no,41,83',
        ];
        $path = $this->dir.'/autores.csv';
        file_put_contents($path, implode("\n", $lines));

        $result = (new ProductImporter(1))->import($path);

        $cartero = Product::where('name', 'Cartero')->first()->bookDetail;
        $this->assertSame('Charles Bukowski', $cartero->author);
        $this->assertSame('Poeta de Los Ángeles.', $cartero->author_bio);
        // The next author starts a new group and does not inherit the previous biography.
        $this->assertSame('Alguien Más', Product::where('name', 'Sin dato')->first()->bookDetail->author);
        $this->assertNull(Product::where('name', 'Sin dato')->first()->bookDetail->author_bio);
        $this->assertSame(['Cartero ← Charles Bukowski', 'Sin dato ← Alguien Más'], $result['inherited']);
    }

    public function test_a_row_without_title_after_a_book_is_a_variant_and_changes_nothing(): void
    {
        $lines = [
            'STOCK,TÍTULO,AUTOR,GÉNERO,EDITORIAL,ISBN,PÁGINAS,TAMAÑO,FORMATO,INFO LIBRO,INFO AUTOR,WEB,PDC,PVP',
            '1,Libro gris,Autor,novela,Anagrama (gris),9788433998224,280,22 x 14,tapa blanda,x,,no,45,90',
            '1,,,,Anagrama (azul),9788433906328,288,20 x 13,tapa blanda,,,no,41,82',
        ];
        $path = $this->dir.'/variantes.csv';
        file_put_contents($path, implode("\n", $lines));

        $result = (new ProductImporter(1))->import($path);

        $this->assertSame(1, $result['created']);
        $this->assertSame([], $result['skipped']);
        $this->assertCount(1, $result['variants']);
        $this->assertStringContainsString('«Libro gris»', $result['variants'][0]);
        $this->assertSame(1, Product::count());
        $this->assertSame(1, Product::where('sku', 'HB-9788433998224')->value('stock'));
        $this->assertNull(Product::where('sku', 'HB-9788433906328')->first());
    }

    public function test_a_title_less_row_with_no_book_before_it_is_skipped(): void
    {
        $path = $this->dir.'/solo.csv';
        file_put_contents($path, "STOCK,TÍTULO,AUTOR,GÉNERO,EDITORIAL,ISBN,PÁGINAS,TAMAÑO,FORMATO,INFO LIBRO,INFO AUTOR,WEB,PDC,PVP\n1,,,,Ed,9788433906328,288,20 x 13,tapa blanda,,,no,41,82");

        $result = (new ProductImporter(1))->import($path);

        $this->assertCount(1, $result['skipped']);
        $this->assertSame([], $result['variants']);
    }

    public function test_running_twice_updates_instead_of_duplicating(): void
    {
        $importer = new ProductImporter(2);
        $importer->import($this->csv());
        $second = $importer->import($this->csv());

        $this->assertSame(0, $second['created']);
        $this->assertSame(3, $second['updated']);
        $this->assertSame(3, Product::count());
        $this->assertSame(2, Category::count());
    }

    public function test_a_dry_run_saves_nothing(): void
    {
        $result = (new ProductImporter(2))->import($this->csv(), dryRun: true);

        $this->assertSame(3, $result['created']);
        $this->assertSame(0, Product::count());
        $this->assertSame(0, Category::count());
    }

    public function test_covers_are_matched_by_isbn(): void
    {
        Storage::fake('public');
        file_put_contents($this->dir.'/9788412537109.jpg', 'fake-image');

        $result = (new ProductImporter(2))->import($this->csv(), coversDir: $this->dir);

        $this->assertSame(1, $result['covers']);
        $this->assertSame('products/9788412537109.jpg', Product::where('sku', 'HB-9788412537109')->value('image_path'));
        Storage::disk('public')->assertExists('products/9788412537109.jpg');
        $this->assertNull(Product::where('name', 'Ovnis')->value('image_path'));
    }

    private function csvWithWeb(string ...$webValues): string
    {
        $lines = ['STOCK,TÍTULO,AUTOR,GÉNERO,EDITORIAL,ISBN,PÁGINAS,TAMAÑO,FORMATO,INFO LIBRO,INFO AUTOR,WEB,PDC,PVP'];
        foreach ($webValues as $i => $web) {
            $lines[] = "2,Libro {$i},Autor,novela,Ed,97800000000".sprintf('%02d', $i).",10,20 x 10,tapa blanda,x,,{$web},5,10";
        }
        $path = $this->dir.'/web.csv';
        file_put_contents($path, implode("\n", $lines));

        return $path;
    }

    public function test_the_web_column_sets_the_product_state(): void
    {
        $result = (new ProductImporter(1))->import($this->csvWithWeb('activo', 'oculto', 'sin stock'));

        $this->assertSame([], $result['warnings']);
        $this->assertSame(ProductStatus::ACTIVE, Product::where('name', 'Libro 0')->first()->status);
        $this->assertSame(ProductStatus::HIDDEN, Product::where('name', 'Libro 1')->first()->status);
        // "Sin stock" is derived from the stock count, and these books have stock.
        $this->assertSame(ProductStatus::ACTIVE, Product::where('name', 'Libro 2')->first()->status);
    }

    public function test_a_web_value_that_is_not_a_state_is_ignored_with_a_warning(): void
    {
        $result = (new ProductImporter(1))->import($this->csvWithWeb('no', 'no'));

        $this->assertSame(2, Product::visible()->count());
        $this->assertCount(1, $result['warnings']);
        $this->assertStringContainsString('«no» (2 filas)', $result['warnings'][0]);
    }

    public function test_a_hidden_product_stays_hidden_when_a_later_import_has_no_state(): void
    {
        $importer = new ProductImporter(1);
        $importer->import($this->csvWithWeb('oculto'));
        $importer->import($this->csvWithWeb(''));

        $this->assertSame(ProductStatus::HIDDEN, Product::where('name', 'Libro 0')->first()->status);
    }
}
