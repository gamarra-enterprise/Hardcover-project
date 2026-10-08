<?php

namespace App\Services;

use App\Enums\ProductStatus;
use App\Models\BookDetail;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Imports the books inventory sheet (CSV) into products and book_details.
 *
 * - The SKU is "HB-" plus the ISBN, so it is stable and unique.
 * - TAMAÑO is "alto x ancho" in centimeters; the sheet has no depth and no weight.
 * - The full GÉNERO text is kept in book_details.genres. Only genres that appear in at least
 *   $minGenreCount books become categories (the shop filters).
 * - WEB is the product state: "activo", "oculto" or "sin stock". Any other value (the sheet
 *   currently says "no") is ignored with a warning. "Sin stock" itself comes from the stock count.
 * - The sheet leaves AUTOR (and often INFO AUTOR) blank when consecutive books share an author
 *   (merged cells), so a blank author is taken from the previous book that has one.
 * - A row without title that follows a book is another tone of that same book (for example
 *   "Anagrama (azul)" after "Anagrama (gris)"). It is reported as a variant and changes nothing.
 * - Running it again updates the same products instead of duplicating them.
 */
class ProductImporter
{
    /** Typos and variants found in the sheet, mapped to the right spelling. */
    private const GENRE_FIXES = [
        'ficció' => 'ficción',
        'contemporanéo' => 'contemporáneo',
        'biografíco' => 'biográfico',
        'literatura américana' => 'literatura americana',
        'literatura latinoaméricana' => 'literatura latinoamericana',
    ];

    private const COVER_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    public function __construct(private readonly int $minGenreCount = 8) {}

    /**
     * @return array{created: int, updated: int, skipped: list<string>, variants: list<string>, inherited: list<string>, warnings: list<string>, filter_genres: list<string>, other_genres: int, covers: int}
     */
    public function import(string $csvPath, bool $dryRun = false, ?string $coversDir = null): array
    {
        [$rows, $skipped, $variants, $inherited] = $this->readRows($csvPath);

        $counts = [];
        foreach ($rows as $row) {
            foreach ($row['genre_list'] as $genre) {
                $counts[$genre] = ($counts[$genre] ?? 0) + 1;
            }
        }
        $filterGenres = array_keys(array_filter($counts, fn (int $n) => $n >= $this->minGenreCount));
        arsort($counts);

        $ignoredWeb = [];
        foreach ($rows as $row) {
            if ($row['web_raw'] !== '' && ! $row['web_known']) {
                $ignoredWeb[$row['web_raw']] = ($ignoredWeb[$row['web_raw']] ?? 0) + 1;
            }
        }

        $result = [
            'created' => 0, 'updated' => 0, 'skipped' => $skipped, 'variants' => $variants, 'inherited' => $inherited, 'warnings' => [],
            'filter_genres' => $filterGenres, 'other_genres' => count($counts) - count($filterGenres), 'covers' => 0,
        ];

        foreach ($ignoredWeb as $value => $count) {
            $result['warnings'][] = "Columna WEB: «{$value}» ({$count} filas) no es un estado (activo, oculto, sin stock); se ignora";
        }

        DB::beginTransaction();

        try {
            foreach ($rows as $row) {
                $this->save($row, $filterGenres, $coversDir, $dryRun, $result);
            }
            $dryRun ? DB::rollBack() : DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $filterGenres
     * @param  array<string, mixed>  $result
     */
    private function save(array $row, array $filterGenres, ?string $coversDir, bool $dryRun, array &$result): void
    {
        $sku = 'HB-'.$row['isbn'];
        $product = Product::firstOrNew(['sku' => $sku]);
        $isNew = ! $product->exists;

        $product->fill([
            'name' => $row['title'],
            'description' => $row['description'],
            'price' => $row['price'],
            'cost_price' => $row['cost'],
            'stock' => $row['stock'],
            'height_mm' => $row['height_mm'],
            'width_mm' => $row['width_mm'],
        ]);

        if ($row['web_status']) {
            $product->status = $row['web_status'];
        }

        if ($isNew) {
            $slug = Str::slug($row['title']);
            $product->slug = Product::where('slug', $slug)->exists() ? $slug.'-'.$row['isbn'] : $slug;
        }

        if ($row['size_warning']) {
            $result['warnings'][] = "{$row['title']}: tamaño no reconocido (\"{$row['size_raw']}\")";
        }

        $cover = $coversDir && ! $dryRun ? $this->storeCover($coversDir, $row['isbn']) : null;
        if ($cover) {
            $product->image_path = $cover;
            $result['covers']++;
        }

        $product->save();

        BookDetail::updateOrCreate(['product_id' => $product->id], [
            'isbn_13' => strlen($row['isbn']) === 13 ? $row['isbn'] : null,
            'isbn_10' => strlen($row['isbn']) === 10 ? $row['isbn'] : null,
            'author' => $row['author'],
            'publisher' => $row['publisher'],
            'pages' => $row['pages'],
            'format' => $row['format'],
            'genres' => implode(' / ', $row['genre_list']),
            'author_bio' => $row['author_bio'],
        ]);

        $categoryIds = [];
        foreach (array_intersect($row['genre_list'], $filterGenres) as $genre) {
            $categoryIds[] = Category::firstOrCreate(
                ['slug' => Str::slug($genre)],
                ['name' => Str::ucfirst($genre), 'is_active' => true],
            )->id;
        }
        $product->categories()->syncWithoutDetaching($categoryIds);

        $result[$isNew ? 'created' : 'updated']++;
    }

    private function storeCover(string $dir, string $isbn): ?string
    {
        foreach (self::COVER_EXTENSIONS as $extension) {
            foreach ([$extension, strtoupper($extension)] as $ext) {
                $file = rtrim($dir, '/')."/{$isbn}.{$ext}";
                if (is_file($file)) {
                    return Storage::disk('public')->putFileAs('products', new File($file), "{$isbn}.{$extension}");
                }
            }
        }

        return null;
    }

    /**
     * @return array{0: list<array<string, mixed>>, 1: list<string>, 2: list<string>, 3: list<string>}
     */
    private function readRows(string $path): array
    {
        if (! is_file($path)) {
            throw new InvalidArgumentException("No existe el archivo: {$path}");
        }

        $handle = fopen($path, 'r');
        $header = null;
        $rows = [];
        $skipped = [];
        $variants = [];
        $inherited = [];
        $lastTitle = null;
        $lastAuthor = null;
        $lastBio = null;
        $seen = [];
        $line = 0;

        while (($cells = fgetcsv($handle, escape: '')) !== false) {
            $line++;
            if ($line === 1) {
                $cells[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $cells[0]);
            }

            if ($header === null) {
                $names = array_map(fn ($c) => mb_strtoupper(trim((string) $c)), $cells);
                if (in_array('ISBN', $names, true) && in_array('TÍTULO', $names, true)) {
                    $header = array_flip($names);
                }

                continue;
            }

            if (! array_filter($cells, fn ($c) => trim((string) $c) !== '')) {
                continue;
            }

            $get = fn (string $column) => trim((string) ($cells[$header[$column] ?? -1] ?? ''));
            $title = $get('TÍTULO');
            $isbn = preg_replace('/\D/', '', $get('ISBN'));

            if ($title === '' && $lastTitle !== null) {
                $variants[] = "Fila {$line} ({$get('EDITORIAL')}, ISBN {$isbn}): se trata como el mismo producto que «{$lastTitle}»; no se modificó nada";

                continue;
            }
            if ($title === '') {
                $skipped[] = "Fila {$line}: sin título";

                continue;
            }
            if (! in_array(strlen($isbn), [10, 13], true)) {
                $skipped[] = "Fila {$line} ({$title}): ISBN inválido";

                continue;
            }
            if (isset($seen[$isbn])) {
                $skipped[] = "Fila {$line} ({$title}): ISBN repetido";

                continue;
            }
            $price = $this->money($get('PVP'));
            if ($price === null) {
                $skipped[] = "Fila {$line} ({$title}): precio de venta inválido";

                continue;
            }

            $seen[$isbn] = true;
            $lastTitle = $title;

            $author = $get('AUTOR');
            $bio = $get('INFO AUTOR');
            if ($author === '' && $lastAuthor !== null) {
                $author = $lastAuthor;
                $bio = $bio ?: $lastBio;
                $inherited[] = "{$title} ← {$author}";
            } else {
                $lastAuthor = $author !== '' ? $author : $lastAuthor;
                $lastBio = $author !== '' ? $bio : $lastBio;
            }
            [$height, $width] = $this->size($get('TAMAÑO'));

            $rows[] = [
                'isbn' => $isbn,
                'title' => $title,
                'author' => $author ?: 'Anónimo',
                'publisher' => $get('EDITORIAL') ?: null,
                'pages' => ctype_digit($get('PÁGINAS')) ? (int) $get('PÁGINAS') : null,
                'format' => $get('FORMATO') !== '' ? Str::ucfirst(mb_strtolower($get('FORMATO'))) : null,
                'genre_list' => $this->genres($get('GÉNERO')),
                'description' => $get('INFO LIBRO') ?: null,
                'author_bio' => $bio ?: null,
                'price' => $price,
                'cost' => $this->money($get('PDC')),
                'stock' => ctype_digit($get('STOCK')) ? (int) $get('STOCK') : 0,
                'height_mm' => $height,
                'width_mm' => $width,
                'web_raw' => $get('WEB'),
                'web_known' => $this->webState($get('WEB')) !== false,
                'web_status' => $this->webState($get('WEB')) ?: null,
                'size_raw' => $get('TAMAÑO'),
                'size_warning' => $get('TAMAÑO') !== '' && $height === null,
            ];
        }

        fclose($handle);

        if ($header === null) {
            throw new InvalidArgumentException('No se encontró la fila de encabezados (ISBN, TÍTULO...).');
        }

        return [$rows, $skipped, $variants, $inherited];
    }

    /**
     * @return ProductStatus|null|false the state to apply, null when it follows the stock
     *                                  count ("sin stock"), false when the value is not a state
     */
    private function webState(string $text): ProductStatus|null|false
    {
        return match (mb_strtolower(trim($text))) {
            'activo' => ProductStatus::ACTIVE,
            'oculto' => ProductStatus::HIDDEN,
            'sin stock', 'agotado' => null,
            default => false,
        };
    }

    /** @return list<string> */
    private function genres(string $text): array
    {
        $genres = [];
        foreach (explode('/', $text) as $genre) {
            $genre = mb_strtolower(trim($genre));
            $genre = self::GENRE_FIXES[$genre] ?? $genre;
            if ($genre !== '') {
                $genres[$genre] = true;
            }
        }

        return array_keys($genres);
    }

    /**
     * "24 x 16.5", "19,5 × 16,5" and "23 X 15" are alto x ancho in centimeters.
     *
     * @return array{0: ?int, 1: ?int} height and width in millimeters
     */
    private function size(string $text): array
    {
        if (! preg_match('/^(\d+(?:[.,]\d+)?)\s*[x×X]\s*(\d+(?:[.,]\d+)?)$/u', $text, $m)) {
            return [null, null];
        }

        $mm = fn (string $cm) => (int) round((float) str_replace(',', '.', $cm) * 10);

        return [$mm($m[1]), $mm($m[2])];
    }

    private function money(string $text): ?string
    {
        $text = str_replace(',', '.', trim($text));

        return is_numeric($text) && (float) $text >= 0 ? number_format((float) $text, 2, '.', '') : null;
    }
}
