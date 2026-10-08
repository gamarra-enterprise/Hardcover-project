<?php

namespace App\Console\Commands;

use App\Services\ProductImporter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use InvalidArgumentException;

#[Signature('products:import {file : CSV del inventario} {--covers= : Carpeta con portadas nombradas por ISBN} {--min-genre-count=8 : Libros mínimos para que un género sea filtro} {--dry-run : Muestra el resultado sin guardar}')]
#[Description('Importa los libros del inventario (CSV) como productos')]
class ImportProducts extends Command
{
    public function handle(): int
    {
        try {
            $result = (new ProductImporter((int) $this->option('min-genre-count')))
                ->import($this->argument('file'), (bool) $this->option('dry-run'), $this->option('covers') ?: null);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info($this->option('dry-run') ? 'Simulación: no se guardó nada.' : 'Importación terminada.');
        $this->table(['Creados', 'Actualizados', 'Omitidos', 'Portadas'], [[
            $result['created'], $result['updated'], count($result['skipped']), $result['covers'],
        ]]);
        $this->line('Géneros como filtro ('.count($result['filter_genres']).'): '.implode(', ', $result['filter_genres']));
        $this->line("Géneros solo como texto: {$result['other_genres']}");

        foreach ([...$result['skipped'], ...$result['warnings']] as $message) {
            $this->components->warn($message);
        }

        return self::SUCCESS;
    }
}
