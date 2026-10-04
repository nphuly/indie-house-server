<?php

namespace App\Http\Controllers;

use App\Domain\Backup\Enums\BackupStatus;
use App\Domain\Backup\Models\BackupRecord;
use App\Filament\Pages\BackupManager;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupDownloadController extends Controller
{
    public function __invoke(BackupRecord $backupRecord): BinaryFileResponse
    {
        abort_unless(BackupManager::canAccess(), 403);
        abort_unless($backupRecord->status === BackupStatus::Completed, 404);

        $path = Storage::disk('local')->path($backupRecord->path);
        abort_unless(is_file($path), 404, __('backup.manager.file_not_found'));

        $root = realpath(Storage::disk('local')->path('backups'));
        $real = realpath($path);
        abort_unless($root !== false && $real !== false && str_starts_with($real, $root.DIRECTORY_SEPARATOR), 404);

        return response()->download($real, $backupRecord->filename);
    }
}
