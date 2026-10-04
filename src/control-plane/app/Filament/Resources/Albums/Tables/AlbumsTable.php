<?php

namespace App\Filament\Resources\Albums\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AlbumsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('cover_image')
                    ->label(__('album.cover_image'))
                    ->disk('s3')
                    ->square(),
                TextColumn::make('title')
                    ->label(__('album.title'))
                    ->description(fn ($record): ?string => $record->artist?->name)
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('tracks_count')
                    ->label(__('album.tracks_count'))
                    ->counts('tracks')
                    ->badge()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('album.created_at'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('artist_id')
                    ->label(__('album.artist'))
                    ->relationship('artist', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
