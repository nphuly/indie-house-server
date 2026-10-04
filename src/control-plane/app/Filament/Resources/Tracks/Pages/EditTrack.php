<?php

namespace App\Filament\Resources\Tracks\Pages;

use App\Filament\Resources\Tracks\TrackResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditTrack extends EditRecord
{
    protected static string $resource = TrackResource::class;

    protected function afterSave(): void
    {
        // If the `raw_file_key` was changed, re-process the track.
        if ($this->record->wasChanged('raw_file_key')) {
            $this->record->dispatchProcessing();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
