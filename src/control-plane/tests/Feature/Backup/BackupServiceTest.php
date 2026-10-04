<?php

namespace Tests\Feature\Backup;

use App\Domain\Backup\Enums\BackupStatus;
use App\Domain\Backup\Models\BackupRecord;
use App\Domain\Backup\Services\BackupService;
use App\Models\Setting;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class BackupServiceTest extends TestCase
{
    private const KEY = 'test-backup-key-0123456789abcdef0123456789';

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('settings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('group');
            $table->string('key');
            $table->text('value')->nullable();
            $table->string('type')->default('string');
            $table->timestamps();
        });

        Schema::create('backup_records', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('filename')->default('');
            $table->string('path')->default('');
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('status')->default('pending');
            $table->string('type')->default('manual');
            $table->text('error_message')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        config(['backup.encryption_key' => self::KEY]);
    }

    public function test_it_creates_a_restorable_archive_on_sqlite(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('example.txt', 'local data');
        $record = BackupRecord::create();

        $service = app(BackupService::class);
        $service->run($record);
        $record->refresh();

        $this->assertSame(BackupStatus::Completed, $record->status);
        Storage::disk('local')->assertExists($record->path);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open(Storage::disk('local')->path($record->path)) === true);
        $this->assertNotFalse($zip->locateName('database.sql'));
        $this->assertNotFalse($zip->locateName('manifest.json'));
        $this->assertNotFalse($zip->locateName('settings.json'));
        $this->assertNotFalse($zip->locateName('storage/example.txt'));
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $this->assertSame(ZipArchive::EM_AES_256, $zip->statIndex($index)['encryption_method']);
        }
        $this->assertFalse($zip->getFromName('storage/example.txt'));
        $zip->setPassword(self::KEY);
        $this->assertSame('local data', $zip->getFromName('storage/example.txt'));
        $zip->close();

        Storage::disk('local')->put('example.txt', 'changed after backup');
        Setting::create([
            'group' => 'test',
            'key' => 'created_after_backup',
            'value' => 'yes',
            'type' => 'string',
        ]);

        $service->restore($record);

        $this->assertSame('local data', Storage::disk('local')->get('example.txt'));
        $this->assertDatabaseMissing('settings', ['key' => 'created_after_backup']);
        $this->assertSame(BackupStatus::Completed, $record->fresh()->status);
    }

    public function test_it_refuses_to_write_a_backup_without_a_key(): void
    {
        Storage::fake('local');
        config(['backup.encryption_key' => 'too-short']);
        $record = BackupRecord::create();

        try {
            app(BackupService::class)->run($record);
            $this->fail('A backup was written without an encryption key.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('BACKUP_ENCRYPTION_KEY', $exception->getMessage());
        }

        $this->assertSame(BackupStatus::Failed, $record->fresh()->status);
        $this->assertSame([], Storage::disk('local')->files('backups'));
    }

    public function test_restoring_with_another_key_fails_without_touching_data(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('example.txt', 'local data');
        $record = BackupRecord::create();
        $service = app(BackupService::class);
        $service->run($record);
        Storage::disk('local')->put('example.txt', 'changed after backup');

        config(['backup.encryption_key' => str_repeat('x', 40)]);

        try {
            $service->restore($record->fresh());
            $this->fail('An archive was restored with the wrong key.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('BACKUP_ENCRYPTION_KEY', $exception->getMessage());
        }

        $this->assertSame('changed after backup', Storage::disk('local')->get('example.txt'));
    }

    public function test_an_archive_made_before_encryption_still_restores(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('example.txt', 'local data');
        $record = BackupRecord::create();
        $service = app(BackupService::class);
        $service->run($record);
        $record->refresh();

        // Rewrite the archive the way backups were written before encryption.
        $path = Storage::disk('local')->path($record->path);
        $encrypted = new ZipArchive;
        $encrypted->open($path);
        $encrypted->setPassword(self::KEY);
        $entries = [];
        for ($index = 0; $index < $encrypted->numFiles; $index++) {
            $name = $encrypted->getNameIndex($index);
            $entries[$name] = $encrypted->getFromIndex($index);
        }
        $encrypted->close();
        $plain = new ZipArchive;
        $plain->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ($entries as $name => $contents) {
            $plain->addFromString($name, $contents);
        }
        $plain->close();

        Storage::disk('local')->put('example.txt', 'changed after backup');
        config(['backup.encryption_key' => null]);
        $service->restore($record);

        $this->assertSame('local data', Storage::disk('local')->get('example.txt'));
    }
}
