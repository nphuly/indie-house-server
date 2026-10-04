<?php

namespace App\Filament\Resources\Artists\Pages;

use App\Filament\Concerns\PromotesPendingUploads;
use App\Filament\Resources\Artists\ArtistResource;
use Filament\Resources\Pages\CreateRecord;

class CreateArtist extends CreateRecord
{
    use PromotesPendingUploads;

    protected static string $resource = ArtistResource::class;

    protected function afterCreate(): void
    {
        foreach (['avatar', 'cover_image'] as $field) {
            $path = $this->record->{$field};
            if (!$path || !str_starts_with($path, 'artists/_pending/')) {
                continue;
            }
            $newPath = str_replace('artists/_pending/', "artists/{$this->record->id}/", $path);
            $this->promotePendingUpload($path, $newPath);
            $this->record->updateQuietly([$field => $newPath]);
        }

        $this->record->refresh();
    }
}
