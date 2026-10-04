<?php

namespace App\Jobs;

use App\Domain\Backup\Services\BackupService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CleanOldBackupsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function handle(BackupService $service): void
    {
        $service->clean();
    }
}
