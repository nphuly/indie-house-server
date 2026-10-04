<?php

namespace app\Filament\Resources\Artists\Schemas\Components;

use App\Models\Media\Artist;
use Filament\Schemas\Components\Component;
use Illuminate\Support\Facades\Storage;

class ArtistProfileHeader extends Component
{
    protected string $view = 'filament.schemas.components.artist-profile-header';

    public static function make(): static
    {
        return app(static::class);
    }

    public function getCoverImageUrl(): ?string
    {
        /** @var Artist $record */
        $record = $this->getRecord();

        if (! $record?->cover_image) {
            return null;
        }

        return Storage::disk('s3')->temporaryUrl($record->cover_image, now()->addMinutes(5));
    }

    public function getAvatarUrl(): ?string
    {
        /** @var Artist $record */
        $record = $this->getRecord();

        if (! $record?->avatar) {
            return null;
        }

        return Storage::disk('s3')->temporaryUrl($record->avatar, now()->addMinutes(5));
    }
}
