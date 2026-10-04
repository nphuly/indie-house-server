<?php

namespace App\Jobs;

use App\Domain\Backup\Enums\BackupStatus;
use App\Domain\Backup\Models\BackupRecord;
use App\Domain\Backup\Services\BackupService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class RunBackupJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(private readonly string $recordId) {}

    public function handle(BackupService $service): void
    {
        $service->run(BackupRecord::findOrFail($this->recordId));
    }

    public function failed(\Throwable $exception): void
    {
        BackupRecord::whereKey($this->recordId)->update([
            'status' => BackupStatus::Failed->value,
            'error_message' => $exception->getMessage(),
        ]);

        Log::error("RunBackupJob failed for record {$this->recordId}: {$exception->getMessage()}");
    }
}
