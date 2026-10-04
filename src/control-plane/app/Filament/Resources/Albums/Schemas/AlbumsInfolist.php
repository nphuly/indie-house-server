<?php

namespace App\Filament\Resources\Albums\Schemas;

use App\Models\Media\Track;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AlbumsInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('album.sections.basic'))
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        ImageEntry::make('cover_image')
                            ->label(__('album.cover_image'))
                            ->disk('s3')
                            ->imageSize(128),
                        TextEntry::make('title')
                            ->label(__('album.title'))
                            ->weight('medium'),
                        TextEntry::make('artist.name')
                            ->label(__('album.artist')),
                        TextEntry::make('slug')
                            ->label(__('album.slug'))
                            ->copyable(),
                        TextEntry::make('public_id')
                            ->label(__('album.public_id'))
                            ->copyable(),
                        TextEntry::make('tracks_count')
                            ->label(__('album.tracks_count'))
                            ->state(fn ($record): int => $record->tracks()->count())
                            ->numeric(),
                        TextEntry::make('created_at')
                            ->label(__('album.created_at'))
                            ->dateTime('d/m/Y H:i'),
                        TextEntry::make('description')
                            ->label(__('album.description'))
                            ->placeholder('-')
                            ->formatStateUsing(fn (?string $state): string => filled($state) ? nl2br(e($state)) : '-')
                            ->html()
                            ->columnSpanFull(),
                    ]),
                Section::make(__('album.sections.tracks'))
                    ->columnSpanFull()
                    ->schema([
                        RepeatableEntry::make('tracks')
                            ->hiddenLabel()
                            ->table([
                                TableColumn::make(__('track.general.title')),
                                TableColumn::make(__('track.general.status')),
                                TableColumn::make(__('track.general.genre')),
                                TableColumn::make(__('track.general.duration')),
                                TableColumn::make(__('track.general.play_count')),
                            ])
                            ->schema([
                                TextEntry::make('title')
                                    ->weight('medium'),
                                TextEntry::make('status')
                                    ->badge()
                                    ->formatStateUsing(fn (?string $state): string => Track::statusLabel($state))
                                    ->color(fn (?string $state): string => Track::statusColor($state)),
                                TextEntry::make('genre')
                                    ->formatStateUsing(fn (?string $state): string => $state ? __("genre.{$state}") : '-'),
                                TextEntry::make('formatted_duration'),
                                TextEntry::make('play_count')
                                    ->numeric(),
                            ])
                            ->contained(false),
                    ]),
            ]);
    }
}
