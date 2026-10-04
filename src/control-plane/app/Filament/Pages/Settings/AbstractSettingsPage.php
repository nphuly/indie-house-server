<?php

namespace App\Filament\Pages\Settings;

use App\Models\Setting;
use App\Support\Settings;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use UnitEnum;

abstract class AbstractSettingsPage extends Page
{
    public ?array $data = [];

    abstract public static function group(): string;

    /** @return array<Component> */
    abstract protected function fields(): array;

    /** @return array<string, mixed> */
    protected function defaults(): array
    {
        return [];
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [];
    }

    public function mount(): void
    {
        $stored = Setting::group(static::group())->pluck('value', 'key')->toArray();

        foreach ($this->casts() as $key => $type) {
            if (! array_key_exists($key, $stored)) {
                continue;
            }

            $stored[$key] = match ($type) {
                'boolean' => filter_var($stored[$key], FILTER_VALIDATE_BOOLEAN),
                'integer' => (int) $stored[$key],
                'json' => json_decode($stored[$key], true),
                default => $stored[$key],
            };
        }

        $this->data = array_merge($this->defaults(), $stored);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->statePath('data')->components($this->fields());
    }

    public function save(): void
    {
        foreach ($this->data ?? [] as $key => $value) {
            $type = $this->casts()[$key] ?? $this->inferType($value);

            Setting::updateOrCreate(
                ['group' => static::group(), 'key' => $key],
                ['value' => $this->serializeValue($value, $type), 'type' => $type],
            );
        }

        Settings::forget(static::group());
        Notification::make()->title(__('backup.settings.saved'))->success()->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label(__('backup.settings.save'))
                ->icon('heroicon-o-check')
                ->action('save'),
        ];
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('backup.navigation.group');
    }

    private function inferType(mixed $value): string
    {
        return match (true) {
            is_bool($value) => 'boolean',
            is_int($value) => 'integer',
            is_array($value) => 'json',
            default => 'string',
        };
    }

    private function serializeValue(mixed $value, string $type): string
    {
        return match ($type) {
            'boolean' => $value ? '1' : '0',
            'json' => json_encode($value, JSON_THROW_ON_ERROR),
            default => (string) ($value ?? ''),
        };
    }
}
