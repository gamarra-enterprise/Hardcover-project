<?php

namespace App\Console\Commands;

use App\Services\DatabaseBackup;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('db:restore {name : Nombre del respaldo (mira `db:backup` o el panel)} {--force : Confirma sin preguntar}')]
#[Description('Reemplaza TODA la base de datos con un respaldo. Solo por consola.')]
class RestoreDatabase extends Command
{
    public function handle(DatabaseBackup $backups): int
    {
        if (! $this->option('force') && ! $this->confirm("Esto reemplaza toda la base de datos con {$this->argument('name')}. ¿Continuar?")) {
            return self::FAILURE;
        }

        try {
            $backups->restore($this->argument('name'));
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Base de datos restaurada.');

        return self::SUCCESS;
    }
}
