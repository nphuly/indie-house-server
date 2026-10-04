<?php

namespace App\Filament\Pages\Settings;

use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class BackupSettings extends AbstractSettingsPage
{
    use HasPageShield;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Clock;

    protected static ?int $navigationSort = 51;

    public static function group(): string
    {
        return 'backup';
    }

    public function getTitle(): string|Htmlable
    {
        return __('backup.settings.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('backup.settings.nav_label');
    }

    protected function defaults(): array
    {
        return [
            'schedule_enabled' => false,
            'schedule_cron' => '0 2 * * *',
            'clean_enabled' => false,
            'clean_cron' => '0 3 * * *',
            'keep_last_n' => 10,
            'keep_days' => 30,
            'offsite_disk' => '',
        ];
    }

    protected function casts(): array
    {
        return [
            'schedule_enabled' => 'boolean',
            'clean_enabled' => 'boolean',
            'keep_last_n' => 'integer',
            'keep_days' => 'integer',
        ];
    }

    protected function fields(): array
    {
        return [
            Section::make(__('backup.settings.sections.schedule'))
                ->schema([
                    Toggle::make('schedule_enabled')
                        ->label(__('backup.settings.fields.schedule_enabled'))
                        ->onColor('success')
                        ->live(),
                    TextInput::make('schedule_cron')
                        ->label(__('backup.settings.fields.schedule_cron'))
                        ->placeholder('0 2 * * *')
                        ->helperText(__('backup.settings.helper_texts.schedule_cron'))
                        ->visible(fn (Get $get): bool => (bool) $get('schedule_enabled'))
                        ->required(fn (Get $get): bool => (bool) $get('schedule_enabled')),
                ]),
            Section::make(__('backup.settings.sections.housekeeping'))
                ->schema([
                    Toggle::make('clean_enabled')
                        ->label(__('backup.settings.fields.clean_enabled'))
                        ->onColor('success')
                        ->live(),
                    TextInput::make('clean_cron')
                        ->label(__('backup.settings.fields.clean_cron'))
                        ->placeholder('0 3 * * *')
                        ->helperText(__('backup.settings.helper_texts.clean_cron'))
                        ->visible(fn (Get $get): bool => (bool) $get('clean_enabled'))
                        ->required(fn (Get $get): bool => (bool) $get('clean_enabled')),
                ]),
            Section::make(__('backup.settings.sections.retention'))
                ->columns(2)
                ->schema([
                    TextInput::make('keep_last_n')
                        ->label(__('backup.settings.fields.keep_last_n'))
                        ->numeric()
                        ->minValue(1)
                        ->required()
                        ->helperText(__('backup.settings.helper_texts.keep_last_n')),
                    TextInput::make('keep_days')
                        ->label(__('backup.settings.fields.keep_days'))
                        ->numeric()
                        ->minValue(1)
                        ->required()
                        ->helperText(__('backup.settings.helper_texts.keep_days')),
                    TextInput::make('offsite_disk')
                        ->label(__('backup.settings.fields.offsite_disk'))
                        ->placeholder('s3')
                        ->helperText(__('backup.settings.helper_texts.offsite_disk'))
                        ->columnSpanFull(),
                ]),
        ];
    }
}
