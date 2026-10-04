<?php

namespace App\Domain\Backup\Services;

use App\Domain\Backup\Enums\BackupStatus;
use App\Domain\Backup\Enums\BackupType;
use App\Domain\Backup\Models\BackupRecord;
use App\Models\Setting;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Symfony\Component\Process\Process;
use ZipArchive;

class BackupService
{
    public function createManualRecord(?int $createdBy): BackupRecord
    {
        return BackupRecord::create([
            'type' => BackupType::Manual,
            'created_by' => $createdBy,
        ]);
    }

    public function run(BackupRecord $record): void
    {
        $record->update([
            'status' => BackupStatus::Running,
            'error_message' => null,
        ]);

        $tempDir = sys_get_temp_dir().'/indie_house_backup_'.$record->id;

        try {
            $key = $this->encryptionKey();
            $this->createDirectory($tempDir);
            $this->createDirectory($tempDir.'/storage');

            $this->dumpDatabase($tempDir.'/database.sql');
            $this->copyLocalStorage($tempDir.'/storage');
            $this->exportSettings($tempDir.'/settings.json');
            $this->writeManifest($tempDir.'/manifest.json', $record);

            $filename = 'backup-'.now()->format('Y-m-d-His').'-'.substr($record->id, 0, 8).'.zip';
            $backupsDir = Storage::disk('local')->path('backups');
            $this->createDirectory($backupsDir);

            $zipPath = $backupsDir.'/'.$filename;
            $this->createZip($tempDir, $zipPath, $key);

            $record->update([
                'status' => BackupStatus::Completed,
                'filename' => $filename,
                'path' => 'backups/'.$filename,
                'size_bytes' => filesize($zipPath) ?: 0,
            ]);

            Log::info("Backup completed: {$filename} ({$record->size_bytes} bytes)");
            $this->copyToOffsite($record);
        } catch (\Throwable $exception) {
            $record->update([
                'status' => BackupStatus::Failed,
                'error_message' => $exception->getMessage(),
            ]);
            Log::error("Backup failed for record {$record->id}: {$exception->getMessage()}");

            throw $exception;
        } finally {
            $this->removeDirectory($tempDir);
        }
    }

    public function copyToOffsite(BackupRecord $record): void
    {
        $disk = Settings::get('backup', 'offsite_disk');

        if (empty($disk) || $disk === 'local' || empty($record->path)) {
            return;
        }

        $stream = null;

        try {
            $stream = Storage::disk('local')->readStream($record->path);

            if ($stream === false || ! Storage::disk((string) $disk)->writeStream($record->path, $stream)) {
                throw new RuntimeException("Unable to write backup to disk [{$disk}].");
            }

            Log::info("Backup {$record->filename} copied to offsite disk [{$disk}].");
        } catch (\Throwable $exception) {
            Log::warning("Backup offsite copy to [{$disk}] failed for {$record->filename}: {$exception->getMessage()}");
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    public function restore(BackupRecord $record): void
    {
        $zipPath = Storage::disk('local')->path($record->path);

        if (! is_file($zipPath)) {
            throw new RuntimeException("Backup file not found: {$record->path}");
        }

        $tempDir = sys_get_temp_dir().'/indie_house_restore_'.$record->id;

        try {
            $this->createDirectory($tempDir);
            $this->extractZipSafely($zipPath, $tempDir);

            if (! is_file($tempDir.'/manifest.json') || ! is_file($tempDir.'/database.sql')) {
                throw new RuntimeException('The backup archive is missing its manifest or database dump.');
            }

            $this->importDatabase($tempDir.'/database.sql');

            // The dump contains this row while it was still running. Preserve the
            // current archive metadata so it remains usable after a restore.
            BackupRecord::whereKey($record->id)->update([
                'status' => BackupStatus::Completed->value,
                'filename' => $record->filename,
                'path' => $record->path,
                'size_bytes' => $record->size_bytes,
                'error_message' => null,
            ]);

            if (is_dir($tempDir.'/storage')) {
                $this->restoreLocalStorage($tempDir.'/storage');
            }

            if (is_file($tempDir.'/settings.json')) {
                $this->importSettings($tempDir.'/settings.json');
            }

            Log::info("Restore completed from: {$record->filename}");
        } finally {
            $this->removeDirectory($tempDir);
        }
    }

    public function clean(): int
    {
        $keepLastN = max(1, (int) Settings::get('backup', 'keep_last_n', 10));
        $keepDays = max(1, (int) Settings::get('backup', 'keep_days', 30));

        $completed = BackupRecord::where('status', BackupStatus::Completed)
            ->latest()
            ->get();

        $byCount = $completed->slice($keepLastN);
        $byAge = BackupRecord::where('status', BackupStatus::Completed)
            ->where('created_at', '<', now()->subDays($keepDays))
            ->get();
        $toDelete = $byCount->merge($byAge)->unique('id');

        foreach ($toDelete as $record) {
            $this->deleteRecord($record);
        }

        BackupRecord::whereIn('status', [
            BackupStatus::Failed->value,
            BackupStatus::Pending->value,
            BackupStatus::Running->value,
        ])->where('created_at', '<', now()->subDays(7))->delete();

        Log::info("Backup housekeeping: deleted {$toDelete->count()} record(s).");

        return $toDelete->count();
    }

    public function scanDirectory(): int
    {
        $disk = Storage::disk('local');
        $files = collect($disk->files('backups'))
            ->filter(fn (string $file): bool => str_ends_with(strtolower($file), '.zip'));
        $existing = BackupRecord::whereIn('filename', $files->map(fn (string $file): string => basename($file)))
            ->pluck('filename')
            ->flip();
        $created = 0;

        foreach ($files as $path) {
            $filename = basename($path);

            if ($existing->has($filename)) {
                continue;
            }

            BackupRecord::create([
                'filename' => $filename,
                'path' => $path,
                'size_bytes' => $disk->size($path),
                'status' => BackupStatus::Completed,
                'type' => BackupType::Manual,
            ]);
            $created++;
        }

        return $created;
    }

    public function deleteRecord(BackupRecord $record): void
    {
        if ($record->path && Storage::disk('local')->exists($record->path)) {
            Storage::disk('local')->delete($record->path);
        }

        $record->delete();
    }

    /** @return list<string> */
    public function insertableColumns(string $table): array
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            throw new RuntimeException('Insertable-column inspection is only used by SQLite backups.');
        }

        return collect(DB::select('PRAGMA table_xinfo('.$this->quoteSqliteIdentifier($table).')'))
            ->reject(fn (object $column): bool => (int) $column->hidden >= 2)
            ->map(fn (object $column): string => (string) $column->name)
            ->values()
            ->all();
    }

    private function dumpDatabase(string $outputPath): void
    {
        match (DB::connection()->getDriverName()) {
            'pgsql' => $this->dumpPostgresDatabase($outputPath),
            'sqlite' => $this->dumpSqliteDatabase($outputPath),
            default => throw new RuntimeException('Backups support PostgreSQL and SQLite only.'),
        };
    }

    private function dumpPostgresDatabase(string $outputPath): void
    {
        $connection = $this->databaseConfig();
        $command = [
            'pg_dump',
            '--host='.(string) $connection['host'],
            '--port='.(string) $connection['port'],
            '--username='.(string) $connection['username'],
            '--dbname='.(string) $connection['database'],
            '--format=plain',
            '--clean',
            '--if-exists',
            '--no-owner',
            '--no-privileges',
            '--file='.$outputPath,
        ];

        $this->runDatabaseProcess($command, $connection);
    }

    private function dumpSqliteDatabase(string $outputPath): void
    {
        $pdo = DB::getPdo();
        $output = fopen($outputPath, 'wb');

        if ($output === false) {
            throw new RuntimeException("Cannot create database dump: {$outputPath}");
        }

        fwrite($output, "PRAGMA foreign_keys=OFF;\nBEGIN TRANSACTION;\n");
        $objects = DB::select("SELECT type, name, sql FROM sqlite_master WHERE sql IS NOT NULL AND name NOT LIKE 'sqlite_%' ORDER BY CASE type WHEN 'table' THEN 0 WHEN 'index' THEN 1 WHEN 'trigger' THEN 2 ELSE 3 END");

        foreach ($objects as $object) {
            if ($object->type !== 'table') {
                continue;
            }

            $table = (string) $object->name;
            $quotedTable = $this->quoteSqliteIdentifier($table);
            fwrite($output, "DROP TABLE IF EXISTS {$quotedTable};\n{$object->sql};\n");
            $insertableColumns = $this->insertableColumns($table);
            $columnList = implode(', ', array_map($this->quoteSqliteIdentifier(...), $insertableColumns));
            $rows = $pdo->query("SELECT {$columnList} FROM {$quotedTable}");

            while ($row = $rows->fetch(\PDO::FETCH_ASSOC)) {
                $values = implode(', ', array_map(
                    fn (mixed $value): string => $value === null ? 'NULL' : $pdo->quote((string) $value),
                    array_values($row),
                ));
                fwrite($output, "INSERT INTO {$quotedTable} ({$columnList}) VALUES ({$values});\n");
            }
        }

        foreach ($objects as $object) {
            if ($object->type !== 'table') {
                fwrite($output, $object->sql.";\n");
            }
        }

        fwrite($output, "COMMIT;\nPRAGMA foreign_keys=ON;\n");
        fclose($output);
    }

    private function importDatabase(string $sqlPath): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            $connection = $this->databaseConfig();
            $this->runDatabaseProcess([
                'psql',
                '--host='.(string) $connection['host'],
                '--port='.(string) $connection['port'],
                '--username='.(string) $connection['username'],
                '--dbname='.(string) $connection['database'],
                '--set=ON_ERROR_STOP=1',
                '--file='.$sqlPath,
            ], $connection);

            return;
        }

        if (DB::connection()->getDriverName() === 'sqlite') {
            $sql = file_get_contents($sqlPath);

            if ($sql === false) {
                throw new RuntimeException('Cannot read the SQLite database dump.');
            }

            DB::unprepared($sql);

            return;
        }

        throw new RuntimeException('Restores support PostgreSQL and SQLite only.');
    }

    /** @return array<string, mixed> */
    private function databaseConfig(): array
    {
        return config('database.connections.'.config('database.default'));
    }

    /** @param array<int, string> $command @param array<string, mixed> $connection */
    private function runDatabaseProcess(array $command, array $connection): void
    {
        $process = new Process($command, null, [
            'PGPASSWORD' => (string) ($connection['password'] ?? ''),
        ]);
        $process->setTimeout(540);
        $process->mustRun();
    }

    private function copyLocalStorage(string $destination): void
    {
        $disk = Storage::disk('local');

        foreach ($disk->allFiles() as $file) {
            if (str_starts_with($file, 'backups/')) {
                continue;
            }

            $destinationPath = $destination.'/'.$file;
            $this->createDirectory(dirname($destinationPath));
            $stream = $disk->readStream($file);

            if ($stream === false) {
                throw new RuntimeException("Cannot read local storage file: {$file}");
            }

            $target = fopen($destinationPath, 'wb');

            if ($target === false) {
                fclose($stream);
                throw new RuntimeException("Cannot copy local storage file: {$file}");
            }

            stream_copy_to_stream($stream, $target);
            fclose($stream);
            fclose($target);
        }
    }

    private function restoreLocalStorage(string $source): void
    {
        $disk = Storage::disk('local');
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY,
        );

        foreach ($files as $file) {
            if ($file->isDir()) {
                continue;
            }

            $relativePath = substr($file->getPathname(), strlen($source) + 1);

            if (str_starts_with($relativePath, 'backups/')) {
                continue;
            }

            $stream = fopen($file->getPathname(), 'rb');

            if ($stream === false || ! $disk->writeStream($relativePath, $stream)) {
                if (is_resource($stream)) {
                    fclose($stream);
                }

                throw new RuntimeException("Cannot restore local storage file: {$relativePath}");
            }

            fclose($stream);
        }
    }

    private function exportSettings(string $outputPath): void
    {
        $settings = Setting::all(['group', 'key', 'value', 'type'])->toArray();
        file_put_contents($outputPath, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    private function importSettings(string $jsonPath): void
    {
        $contents = file_get_contents($jsonPath);
        $settings = $contents === false ? null : json_decode($contents, true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($settings)) {
            return;
        }

        $groups = [];

        foreach ($settings as $item) {
            if (! isset($item['group'], $item['key'])) {
                continue;
            }

            Setting::updateOrCreate(
                ['group' => $item['group'], 'key' => $item['key']],
                ['value' => $item['value'] ?? null, 'type' => $item['type'] ?? 'string'],
            );
            $groups[] = $item['group'];
        }

        foreach (array_unique($groups) as $group) {
            Settings::forget($group);
        }
    }

    private function writeManifest(string $outputPath, BackupRecord $record): void
    {
        $manifest = [
            'format_version' => 1,
            'created_at' => now()->toIso8601String(),
            'app_name' => config('app.name'),
            'app_url' => config('app.url'),
            'laravel_version' => app()->version(),
            'db_connection' => config('database.default'),
            'db_database' => config('database.connections.'.config('database.default').'.database'),
            'backup_record_id' => $record->id,
        ];

        file_put_contents($outputPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    /**
     * The archive holds password hashes, emails and listening history, and may
     * be copied offsite, so no backup is written without a key.
     */
    private function encryptionKey(): string
    {
        $key = config('backup.encryption_key');

        if (! is_string($key) || strlen($key) < 32) {
            throw new RuntimeException('BACKUP_ENCRYPTION_KEY must be set to at least 32 characters (openssl rand -base64 32).');
        }

        return $key;
    }

    /**
     * Every entry is encrypted with WinZip AES-256, which 7-Zip opens with the
     * key when the application is not available. Entry names stay readable.
     */
    private function createZip(string $source, string $zipPath, string $key): void
    {
        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Cannot create zip file: {$zipPath}");
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY,
        );

        foreach ($files as $file) {
            if (! $file->isDir()) {
                $name = substr($file->getPathname(), strlen($source) + 1);

                if (! $zip->addFile($file->getPathname(), $name)
                    || ! $zip->setEncryptionName($name, ZipArchive::EM_AES_256, $key)) {
                    throw new RuntimeException("Cannot add {$name} to the encrypted backup archive.");
                }
            }
        }

        if (! $zip->close()) {
            throw new RuntimeException("Cannot finish zip file: {$zipPath}");
        }
    }

    private function extractZipSafely(string $zipPath, string $destination): void
    {
        $zip = new ZipArchive;

        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('Failed to open the backup archive.');
        }

        try {
            $encrypted = false;

            for ($index = 0; $index < $zip->numFiles; $index++) {
                $name = $zip->getNameIndex($index);

                if ($name === false || str_starts_with($name, '/') || preg_match('~(^|[\\\\/])\.\.([\\\\/]|$)~', $name)) {
                    throw new RuntimeException('The backup archive contains an unsafe path.');
                }

                $encrypted = $encrypted || ($zip->statIndex($index)['encryption_method'] ?? ZipArchive::EM_NONE) !== ZipArchive::EM_NONE;
            }

            // Archives made before encryption have none and still restore.
            if ($encrypted) {
                $zip->setPassword($this->encryptionKey());
            }

            if (! $zip->extractTo($destination)) {
                throw new RuntimeException($encrypted
                    ? 'Failed to decrypt the backup archive. Check that BACKUP_ENCRYPTION_KEY is the key it was made with.'
                    : 'Failed to extract the backup archive.');
            }
        } finally {
            $zip->close();
        }
    }

    private function createDirectory(string $path): void
    {
        if (! is_dir($path) && ! mkdir($path, 0775, true) && ! is_dir($path)) {
            throw new RuntimeException("Cannot create directory: {$path}");
        }
    }

    private function removeDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($path);
    }

    private function quoteSqliteIdentifier(string $identifier): string
    {
        return '"'.str_replace('"', '""', $identifier).'"';
    }
}
