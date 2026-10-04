<?php

namespace Tests\Unit\Backup;

use App\Domain\Backup\Models\BackupRecord;
use App\Domain\Backup\Services\BackupService;
use App\Models\Setting;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackupOffsiteTest extends TestCase
{
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
            $table->unique(['group', 'key']);
        });
    }

    public function test_completed_backup_is_copied_to_the_configured_offsite_disk(): void
    {
        Storage::fake('local');
        Storage::fake('s3');
        Storage::disk('local')->put('backups/test.zip', 'ZIPDATA');
        Setting::create([
            'group' => 'backup',
            'key' => 'offsite_disk',
            'value' => 's3',
            'type' => 'string',
        ]);
        $record = new BackupRecord(['filename' => 'test.zip', 'path' => 'backups/test.zip']);

        app(BackupService::class)->copyToOffsite($record);

        Storage::disk('s3')->assertExists('backups/test.zip');
        $this->assertSame('ZIPDATA', Storage::disk('s3')->get('backups/test.zip'));
    }
}
