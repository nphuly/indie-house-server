<?php

namespace App\Console\Commands;

use App\Domain\Backup\Services\BackupService;
use Illuminate\Console\Command;

class BackupCleanCommand extends Command
{
    protected $signature = 'backup:clean';

    protected $description = 'Delete old backups according to the retention policy';

    public function handle(BackupService $service): int
    {
        $count = $service->clean();
        $this->info("Backup housekeeping complete. Deleted: {$count} record(s).");

        return self::SUCCESS;
    }
}
