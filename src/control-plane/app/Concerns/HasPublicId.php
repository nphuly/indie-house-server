<?php

namespace App\Concerns;

use Illuminate\Support\Str;

trait HasPublicId
{
    public const int PUBLIC_ID_LENGTH = 12;

    public static function bootHasPublicId(): void
    {
        static::creating(function (self $model): void {
            if (filled($model->public_id)) {
                return;
            }

            do {
                $publicId = Str::random(self::PUBLIC_ID_LENGTH);
            } while (static::query()->where('public_id', $publicId)->exists());

            $model->public_id = $publicId;
        });
    }
}
