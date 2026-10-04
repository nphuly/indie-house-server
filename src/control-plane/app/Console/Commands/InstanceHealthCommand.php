<?php

namespace App\Console\Commands;

use App\Domain\Backup\Models\BackupRecord;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class InstanceHealthCommand extends Command
{
    protected $signature = 'instance:health {--json : Emit one machine-readable JSON result}';

    protected $description = 'Check private instance dependencies and backup freshness without changing data';

    public function handle(): int
    {
        $checks = [];
        $checks['database'] = $this->check(fn () => DB::selectOne('SELECT 1'));
        $checks['redis'] = $this->check(fn () => Redis::connection()->ping());
        $checks['media_storage'] = $this->check(fn () => Storage::disk('s3')->files(''));

        $minimumFree = max(1, (int) env('MONITOR_MIN_DISK_FREE_GB', 10)) * 1024 * 1024 * 1024;
        $free = disk_free_space(storage_path());
        $checks['disk'] = [
            'ok' => is_float($free) && $free >= $minimumFree,
            'free_bytes' => $free === false ? null : (int) $free,
        ];

        $checks['backup'] = $this->checkBackup();
        // Without the key every scheduled backup fails; say so before one does.
        $key = config('backup.encryption_key');
        $checks['backup_encryption'] = ['ok' => is_string($key) && strlen($key) >= 32];
        $checks['database_size_bytes'] = $this->databaseBytes();
        $checks['processed_media_bytes'] = $this->processedMediaBytes();
        $healthy = collect($checks)->filter(fn (mixed $value): bool => is_array($value) && array_key_exists('ok', $value))
            ->every(fn (array $value): bool => $value['ok']);
        $result = ['status' => $healthy ? 'ok' : 'degraded', 'checked_at' => now()->toIso8601String(), 'checks' => $checks];

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_THROW_ON_ERROR));
        } else {
            $this->info(json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        }

        return $healthy ? self::SUCCESS : self::FAILURE;
    }

    /** @param callable(): mixed $probe */
    private function check(callable $probe): array
    {
        try {
            $probe();

            return ['ok' => true];
        } catch (Throwable $exception) {
            return ['ok' => false, 'reason' => class_basename($exception)];
        }
    }

    private function checkBackup(): array
    {
        try {
            if (! Schema::hasTable('backup_records')) {
                return ['ok' => false, 'reason' => 'backup_records_missing'];
            }

            $latest = BackupRecord::query()->where('status', 'completed')->latest('created_at')->first();
            $ageHours = $latest?->created_at?->diffInHours(now());
            $maxHours = max(1, (int) env('MONITOR_BACKUP_MAX_AGE_HOURS', 26));

            return [
                'ok' => $ageHours !== null && $ageHours <= $maxHours,
                'age_hours' => $ageHours,
                'max_age_hours' => $maxHours,
            ];
        } catch (Throwable $exception) {
            return ['ok' => false, 'reason' => class_basename($exception)];
        }
    }

    private function databaseBytes(): ?int
    {
        try {
            return DB::getDriverName() === 'pgsql'
                ? (int) DB::selectOne('SELECT pg_database_size(current_database()) AS bytes')->bytes
                : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function processedMediaBytes(): ?int
    {
        try {
            return Schema::hasTable('track_renditions')
                ? (int) DB::table('track_renditions')->sum('file_size_bytes')
                : null;
        } catch (Throwable) {
            return null;
        }
    }
}
