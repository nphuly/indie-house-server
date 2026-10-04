<?php

namespace App\Filament\Resources\Albums\Pages;

use App\Filament\Concerns\PromotesPendingUploads;
use App\Filament\Resources\Albums\AlbumsResource;
use App\Models\Media\Track;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class CreateAlbums extends CreateRecord
{
    use PromotesPendingUploads;

    protected static string $resource = AlbumsResource::class;

    /** @var array<int, array<string, mixed>> */
    protected array $trackDrafts = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->trackDrafts = $data['track_drafts'] ?? [];

        // Filament drops an upload it cannot find in storage instead of failing.
        foreach ($this->trackDrafts as $draft) {
            if (blank($draft['raw_file_key'] ?? null)) {
                $this->haltWithUploadError();
            }
        }

        unset($data['track_drafts']);

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->moveCoverImage();

        foreach ($this->trackDrafts as $sortOrder => $draft) {
            $track = Track::create([
                ...Arr::only($draft, ['title', 'slug', 'genre', 'lyrics', 'is_published']),
                'artist_id' => $this->record->artist_id,
                'raw_file_key' => $draft['raw_file_key'],
            ]);

            $this->moveTrackFile($track);

            $this->record->tracks()->attach($track->id, ['sort_order' => $sortOrder]);

            DB::afterCommit(fn () => $track->dispatchProcessing(deleteProcessedFiles: false));
        }
    }

    private function moveCoverImage(): void
    {
        $path = $this->record->cover_image;

        if (! $path || ! str_starts_with($path, 'albums/_pending/')) {
            return;
        }

        $newPath = str_replace('albums/_pending/', "albums/{$this->record->id}/", $path);

        $this->promotePendingUpload($path, $newPath);
        $this->record->updateQuietly(['cover_image' => $newPath]);
        $this->record->refresh();
    }

    private function moveTrackFile(Track $track): void
    {
        $path = $track->raw_file_key;

        if (! $path || ! str_starts_with($path, 'tracks/_pending/')) {
            return;
        }

        $newPath = 'tracks/raw/'.now()->format('Y/m')."/{$track->id}/".basename($path);

        $this->promotePendingUpload($path, $newPath);
        $track->updateQuietly(['raw_file_key' => $newPath]);
        $track->refresh();
    }
}
