<?php

namespace App\Console\Commands;

use App\Domain\Backup\Enums\BackupType;
use App\Domain\Backup\Models\BackupRecord;
use App\Jobs\RunBackupJob;
use Illuminate\Console\Command;

class BackupRunCommand extends Command
{
    protected $signature = 'backup:run';

    protected $description = 'Dispatch a database and local-storage backup';

    public function handle(): int
    {
        $record = BackupRecord::create(['type' => BackupType::Scheduled]);
        RunBackupJob::dispatch($record->id);

        $this->info("Backup job dispatched (record: {$record->id}).");

        return self::SUCCESS;
    }
}
