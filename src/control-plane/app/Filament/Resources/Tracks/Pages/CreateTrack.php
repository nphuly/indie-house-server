<?php

namespace App\Filament\Resources\Tracks\Pages;

use App\Filament\Concerns\PromotesPendingUploads;
use App\Filament\Resources\Tracks\TrackResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;

class CreateTrack extends CreateRecord
{
    use PromotesPendingUploads;

    protected static string $resource = TrackResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Filament drops an upload it cannot find in storage instead of failing.
        if (blank($data['raw_file_key'] ?? null)) {
            $this->haltWithUploadError();
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $path = $this->record->raw_file_key;

        if ($path && str_starts_with($path, 'tracks/_pending/')) {
            $newPath = 'tracks/raw/'.now()->format('Y/m')."/{$this->record->id}/".basename($path);

            $this->promotePendingUpload($path, $newPath);
            $this->record->updateQuietly(['raw_file_key' => $newPath]);
            $this->record->refresh();
        }

        if ($this->record->raw_file_key) {
            $track = $this->record;

            // The worker reports back by track ID, so the row must be committed first.
            DB::afterCommit(fn () => $track->dispatchProcessing(deleteProcessedFiles: false));
        }
    }
}
