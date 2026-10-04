<?php

namespace App\Filament\Resources\Tracks\Tables;

use App\Enums\Genre;
use App\Models\Media\Track;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class TracksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label(__('track.general.title'))
                    ->description(fn (Track $record): ?string => $record->artist?->name)
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('status')
                    ->label(__('track.general.status'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => Track::statusLabel($state))
                    ->color(fn (?string $state): string => Track::statusColor($state))
                    ->sortable(),
                TextColumn::make('genre')
                    ->label(__('track.general.genre'))
                    ->formatStateUsing(fn (?string $state): string => $state ? __("genre.{$state}") : '-')
                    ->toggleable(),
                TextColumn::make('formatted_duration')
                    ->label(__('track.general.duration'))
                    ->sortable(query: fn ($query, string $direction) => $query->orderBy('duration_seconds', $direction)),
                TextColumn::make('renditions_count')
                    ->label(__('track.general.renditions_count'))
                    ->counts('renditions')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'gray'),
                ToggleColumn::make('is_published')
                    ->label(__('track.general.is_published')),
                TextColumn::make('play_count')
                    ->label(__('track.general.play_count'))
                    ->numeric()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->label(__('track.general.updated_at'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('artist_id')
                    ->label(__('track.general.artist'))
                    ->relationship('artist', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('status')
                    ->label(__('track.general.status'))
                    ->options([
                        'pending' => __('track.statuses.pending'),
                        'processing' => __('track.statuses.processing'),
                        'completed' => __('track.statuses.completed'),
                        'failed' => __('track.statuses.failed'),
                    ]),
                SelectFilter::make('genre')
                    ->label(__('track.general.genre'))
                    ->options(Genre::class)
                    ->searchable(),
                TernaryFilter::make('is_published')
                    ->label(__('track.general.is_published')),
            ])
            ->recordActions([
                Action::make('preview')
                    ->label(__('track.actions.preview'))
                    ->icon(Heroicon::Play)
                    ->modalHeading(fn (Track $record): string => $record->title)
                    ->modalContent(fn (Track $record) => view('filament.tracks.audio-preview', ['record' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('track.actions.close'))
                    ->visible(fn (Track $record): bool => filled($record->preview_file_key)),
                Action::make('reprocess')
                    ->label(__('track.actions.reprocess'))
                    ->icon(Heroicon::ArrowPath)
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription(__('track.actions.reprocess_description'))
                    ->action(function (Track $record): void {
                        $record->dispatchProcessing();

                        Notification::make()
                            ->title(__('track.notifications.processing_queued'))
                            ->success()
                            ->send();
                    }),
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('reprocess')
                        ->label(__('track.actions.reprocess_selected'))
                        ->icon(Heroicon::ArrowPath)
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $records->each(fn (Track $record) => $record->dispatchProcessing());

                            Notification::make()
                                ->title(__('track.notifications.processing_queued'))
                                ->success()
                                ->send();
                        }),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
