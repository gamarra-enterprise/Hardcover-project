<?php

namespace App\Console\Commands;

use App\Services\DatabaseBackup;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('db:backup {--keep=14 : Cuántos respaldos conservar}')]
#[Description('Crea un respaldo de la base de datos en storage/app/private/backups')]
class BackupDatabase extends Command
{
    public function handle(DatabaseBackup $backups): int
    {
        try {
            $this->info('Respaldo creado: '.$backups->create());
            $pruned = $backups->prune((int) $this->option('keep'));
            $pruned && $this->line("Se eliminaron {$pruned} respaldos antiguos.");
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
