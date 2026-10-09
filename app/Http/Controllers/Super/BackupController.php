<?php

namespace App\Http\Controllers\Super;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Services\DatabaseBackup;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Database backups: list, create, download and delete. Restoring is console-only (`db:restore`). */
class BackupController extends Controller
{
    public function index(DatabaseBackup $backups): View
    {
        return view('super.backups', ['backups' => $backups->all()]);
    }

    public function store(DatabaseBackup $backups): RedirectResponse
    {
        try {
            $name = $backups->create();
        } catch (RuntimeException $e) {
            report($e);

            return back()->with('error', 'No se pudo crear el respaldo. Revisa el registro del servidor.');
        }

        ActivityLog::record('backup.created', "Creó el respaldo {$name}");

        return back()->with('notice', "Respaldo creado: {$name}.");
    }

    public function download(string $name, DatabaseBackup $backups): BinaryFileResponse
    {
        $path = $backups->path($name) ?? abort(404);

        ActivityLog::record('backup.downloaded', "Descargó el respaldo {$name}");

        return response()->download($path, $name, ['Content-Type' => 'application/octet-stream']);
    }

    public function destroy(string $name, DatabaseBackup $backups): RedirectResponse
    {
        abort_unless($backups->delete($name), 404);

        ActivityLog::record('backup.deleted', "Eliminó el respaldo {$name}");

        return back()->with('notice', 'Respaldo eliminado.');
    }
}
