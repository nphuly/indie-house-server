<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PurgePendingMediaUploads extends Command
{
    protected $signature = 'media:purge-pending-uploads
        {--hours=24 : Delete uploads that have been pending for this many hours}
        {--dry-run : List files that would be deleted without deleting them}';

    protected $description = 'Delete abandoned pending artist, album, and track uploads from object storage';

    public function handle(): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $cutoff = now()->subHours($hours)->getTimestamp();
        $storage = Storage::disk('s3');
        $deleted = 0;
        $failed = 0;

        foreach (['artists/_pending', 'albums/_pending', 'tracks/_pending', 'livewire-tmp'] as $directory) {
            try {
                foreach ($storage->getDriver()->listContents($directory, true) as $attributes) {
                    if (! $attributes->isFile() || ($attributes->lastModified() ?? PHP_INT_MAX) > $cutoff) {
                        continue;
                    }

                    $path = $attributes->path();

                    if ($this->option('dry-run')) {
                        $this->line("Would delete: {$path}");

                        continue;
                    }

                    $storage->delete($path);
                    $deleted++;
                }
            } catch (Throwable $exception) {
                report($exception);
                $this->error("Could not clean {$directory}: {$exception->getMessage()}");
                $failed++;
            }
        }

        if ($this->option('dry-run')) {
            $this->info('Dry run completed; no files were deleted.');
        } else {
            $this->info("Deleted {$deleted} abandoned pending upload(s).");
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
