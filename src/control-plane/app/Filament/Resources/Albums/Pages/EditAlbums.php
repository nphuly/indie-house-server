<?php

namespace App\Filament\Resources\Albums\Pages;

use App\Filament\Resources\Albums\AlbumsResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Arr;

class EditAlbums extends EditRecord
{
    protected static string $resource = AlbumsResource::class;

    protected function afterSave(): void
    {
        $trackIds = array_values(array_filter(Arr::wrap($this->data['tracks'] ?? [])));
        $tracksWithOrder = [];

        foreach ($trackIds as $sortOrder => $trackId) {
            $tracksWithOrder[$trackId] = ['sort_order' => $sortOrder];
        }

        $this->record->tracks()->sync($tracksWithOrder);
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
