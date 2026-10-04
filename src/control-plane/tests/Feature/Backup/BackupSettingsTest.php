<?php

namespace Tests\Feature\Backup;

use App\Filament\Pages\Settings\BackupSettings;
use App\Models\Setting;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class BackupSettingsTest extends TestCase
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

    public function test_defaults_and_typed_values_are_saved(): void
    {
        Livewire::test(BackupSettings::class)
            ->assertSet('data.schedule_enabled', false, strict: true)
            ->assertSet('data.schedule_cron', '0 2 * * *')
            ->set('data.schedule_enabled', true)
            ->set('data.keep_last_n', 15)
            ->set('data.offsite_disk', 's3')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('settings', [
            'group' => 'backup',
            'key' => 'schedule_enabled',
            'value' => '1',
            'type' => 'boolean',
        ]);
        $this->assertSame('15', Setting::group('backup')->where('key', 'keep_last_n')->value('value'));
        $this->assertSame('s3', Setting::group('backup')->where('key', 'offsite_disk')->value('value'));
    }
}
