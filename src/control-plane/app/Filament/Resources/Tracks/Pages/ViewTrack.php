<?php

namespace App\Filament\Resources\Tracks\Pages;

use App\Filament\Resources\Tracks\TrackResource;
use App\Models\Media\Track;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewTrack extends ViewRecord
{
    protected static string $resource = TrackResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reprocess')
                ->label(__('track.actions.reprocess'))
                ->icon(Heroicon::ArrowPath)
                ->color('info')
                ->requiresConfirmation()
                ->modalDescription(__('track.actions.reprocess_description'))
                ->action(function (Track $record): void {
                    $record->dispatchProcessing();

                    Notification::make()
                        ->title(__('track.notifications.processing_queued'))
                        ->success()
                        ->send();
                }),
            EditAction::make(),
        ];
    }
}
