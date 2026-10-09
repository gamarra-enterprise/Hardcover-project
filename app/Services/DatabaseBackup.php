<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Backups of the PostgreSQL database with pg_dump (custom format), kept on the private disk.
 * Creating and downloading can be done from the panel; restoring is deliberately console-only
 * (`php artisan db:restore`), because a web button that replaces the whole database is too dangerous.
 * Files hold customer data: they never go in a public folder.
 */
class DatabaseBackup
{
    private const DIR = 'backups';

    private const NAME = '/^hardcover-\d{8}-\d{6}\.dump$/';

    /** @return list<array{name: string, size: int, created_at: \Carbon\CarbonImmutable}> newest first */
    public function all(): array
    {
        $disk = Storage::disk('local');

        return collect($disk->files(self::DIR))
            ->map(fn (string $path) => basename($path))
            ->filter(fn (string $name) => preg_match(self::NAME, $name))
            ->map(fn (string $name) => [
                'name' => $name,
                'size' => $disk->size(self::DIR.'/'.$name),
                'created_at' => \Carbon\CarbonImmutable::createFromTimestamp($disk->lastModified(self::DIR.'/'.$name)),
            ])
            ->sortByDesc('name')
            ->values()
            ->all();
    }

    /** @throws RuntimeException when pg_dump fails */
    public function create(): string
    {
        $name = 'hardcover-'.now()->format('Ymd-His').'.dump';
        $disk = Storage::disk('local');
        $disk->makeDirectory(self::DIR);
        $target = $disk->path(self::DIR.'/'.$name);

        $process = new Process(
            ['pg_dump', '--format=custom', '--no-owner', '--no-privileges', '--file', $target, ...$this->connection()],
            env: ['PGPASSWORD' => (string) config('database.connections.pgsql.password')],
            timeout: 600,
        );
        $process->run();

        if (! $process->isSuccessful() || ! is_file($target) || filesize($target) === 0) {
            @unlink($target);
            throw new RuntimeException('No se pudo crear el respaldo: '.trim($process->getErrorOutput()));
        }

        return $name;
    }

    /** Keep only the newest $keep backups, so the disk does not fill up. */
    public function prune(int $keep): int
    {
        $old = array_slice($this->all(), max(1, $keep));
        foreach ($old as $backup) {
            $this->delete($backup['name']);
        }

        return count($old);
    }

    /** Absolute path of a backup, or null if the name is not a real backup (this also blocks path tricks). */
    public function path(string $name): ?string
    {
        if (! preg_match(self::NAME, $name) || ! Storage::disk('local')->exists(self::DIR.'/'.$name)) {
            return null;
        }

        return Storage::disk('local')->path(self::DIR.'/'.$name);
    }

    public function delete(string $name): bool
    {
        return $this->path($name) !== null && Storage::disk('local')->delete(self::DIR.'/'.$name);
    }

    /** @throws RuntimeException when pg_restore reports an error */
    public function restore(string $name): void
    {
        $path = $this->path($name) ?? throw new RuntimeException("No existe el respaldo {$name}.");

        $process = new Process(
            ['pg_restore', '--clean', '--if-exists', '--no-owner', '--no-privileges', '--exit-on-error', '--single-transaction', ...$this->connection(), $path],
            env: ['PGPASSWORD' => (string) config('database.connections.pgsql.password')],
            timeout: 1800,
        );
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('No se pudo restaurar: '.trim($process->getErrorOutput()));
        }
    }

    /** @return list<string> */
    private function connection(): array
    {
        $c = config('database.connections.pgsql');

        return ['--host', (string) $c['host'], '--port', (string) $c['port'], '--username', (string) $c['username'], '--dbname', (string) $c['database']];
    }
}
