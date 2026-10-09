<?php

namespace App\Services;

use App\Enums\ProductStatus;
use App\Models\Product;

/**
 * Writes the catalog as a CSV with the same columns `ProductImporter` reads, so an export is
 * both a backup and a file that can be edited and imported again. Only books are included:
 * the sheet is keyed by ISBN. Cost price is in it, so the file is for staff only.
 */
class CatalogExporter
{
    public const HEADER = ['ISBN', 'TÍTULO', 'AUTOR', 'EDITORIAL', 'PÁGINAS', 'FORMATO', 'GÉNERO', 'INFO LIBRO', 'INFO AUTOR', 'PVP', 'PDC', 'STOCK', 'TAMAÑO', 'WEB'];

    /** @param  resource  $out */
    public function write($out): int
    {
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, self::HEADER, escape: '');

        $count = 0;

        Product::query()->with('bookDetail')->whereHas('bookDetail')->orderBy('name')->chunk(200, function ($products) use ($out, &$count) {
            foreach ($products as $product) {
                $book = $product->bookDetail;
                $isbn = $book->isbn_13 ?: $book->isbn_10;

                if (! $isbn) {
                    continue;
                }

                fputcsv($out, [
                    $isbn,
                    $this->text($product->name),
                    $this->text($book->author),
                    $this->text($book->publisher),
                    $book->pages,
                    $this->text($book->format),
                    $this->text($book->genres),
                    $this->text($product->description),
                    $this->text($book->author_bio),
                    $product->price,
                    $product->cost_price,
                    $product->stock,
                    $this->size($product),
                    match ($product->status) {
                        ProductStatus::HIDDEN => 'oculto',
                        ProductStatus::OUT_OF_STOCK => 'sin stock',
                        default => 'activo',
                    },
                ], escape: '');
                $count++;
            }
        });

        return $count;
    }

    /** A cell that starts like a formula would run in a spreadsheet, so it gets a leading quote. */
    private function text(?string $value): string
    {
        $value = (string) $value;

        return $value !== '' && str_contains("=+-@\t\r", $value[0]) ? "'".$value : $value;
    }

    /** "alto x ancho" in centimeters, the way the sheet writes it. */
    private function size(Product $product): string
    {
        if (! $product->height_mm || ! $product->width_mm) {
            return '';
        }

        return rtrim(rtrim(number_format($product->height_mm / 10, 1, '.', ''), '0'), '.').' x '
            .rtrim(rtrim(number_format($product->width_mm / 10, 1, '.', ''), '0'), '.');
    }
}
