<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class Settings
{
    public static function get(string $group, string $key, mixed $default = null): mixed
    {
        $all = static::loadGroup($group);

        if (! array_key_exists($key, $all)) {
            return $default;
        }

        return static::cast($all[$key]['value'], $all[$key]['type']);
    }

    /** @return array<string, mixed> */
    public static function all(string $group): array
    {
        return collect(static::loadGroup($group))
            ->mapWithKeys(fn (array $item, string $key): array => [
                $key => static::cast($item['value'], $item['type']),
            ])
            ->all();
    }

    public static function forget(string $group): void
    {
        Cache::forget("settings.{$group}");
    }

    /** @return array<string, array{value: string|null, type: string}> */
    private static function loadGroup(string $group): array
    {
        return Cache::rememberForever("settings.{$group}", fn (): array => Setting::group($group)
            ->get(['key', 'value', 'type'])
            ->mapWithKeys(fn (Setting $setting): array => [
                $setting->key => [
                    'value' => $setting->value,
                    'type' => $setting->type ?? 'string',
                ],
            ])
            ->all());
    }

    private static function cast(mixed $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'integer' => (int) $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($value, true),
            default => $value,
        };
    }
}
