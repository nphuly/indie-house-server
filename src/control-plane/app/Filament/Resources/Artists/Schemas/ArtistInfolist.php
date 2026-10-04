<?php

namespace App\Filament\Resources\Artists\Schemas;

use App\Filament\Resources\Albums\AlbumsResource;
use App\Filament\Resources\Artists\Schemas\Components\ArtistProfileHeader;
use App\Filament\Resources\Tracks\TrackResource;
use App\Models\Media\Album;
use App\Models\Media\Artist;
use App\Models\Media\Playlist;
use App\Models\Media\Track;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ArtistInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                ArtistProfileHeader::make()
                    ->columnSpanFull(),
                TextEntry::make('public_id')
                    ->label(__('artist.public_id'))
                    ->copyable(),
                Tabs::make()
                    ->tabs([
                        Tab::make(__('artist.tabs.popular_tracks'))
                            ->schema([
                                self::tracks('popular_tracks')
                                    ->state(fn (Artist $record): Collection => once(
                                        fn (): Collection => $record->tracks->sortByDesc('play_count')->take(10)->values(),
                                    )),
                            ]),
                        Tab::make(__('artist.tabs.tracks'))
                            ->schema([
                                self::tracks('tracks'),
                            ]),
                        Tab::make(__('artist.tabs.albums'))
                            ->schema([
                                RepeatableEntry::make('albums')
                                    ->hiddenLabel()
                                    ->state(fn (Artist $record): Collection => once(
                                        fn (): Collection => $record->albums()->withCount('tracks')->latest()->get(),
                                    ))
                                    ->placeholder(__('artist.empty.albums'))
                                    ->table([
                                        TableColumn::make(__('album.cover_image')),
                                        TableColumn::make(__('album.title')),
                                        TableColumn::make(__('album.tracks_count')),
                                        TableColumn::make(__('album.created_at')),
                                    ])
                                    ->schema([
                                        ImageEntry::make('cover_image')
                                            ->disk('s3')
                                            ->imageSize(48),
                                        TextEntry::make('title')
                                            ->weight('medium')
                                            ->url(fn (Album $record): string => AlbumsResource::getUrl('view', ['record' => $record])),
                                        TextEntry::make('tracks_count')
                                            ->numeric(),
                                        TextEntry::make('created_at')
                                            ->dateTime('d/m/Y H:i'),
                                    ])
                                    ->contained(false),
                            ]),
                        Tab::make(__('artist.tabs.playlists'))
                            ->schema([
                                // Only public playlists: listeners' private ones stay private.
                                RepeatableEntry::make('public_playlists')
                                    ->hiddenLabel()
                                    ->state(fn (Artist $record): Collection => once(
                                        fn (): Collection => Playlist::query()
                                            ->where('is_public', true)
                                            ->whereHas('tracks', fn (Builder $track) => $track->where('artist_id', $record->id))
                                            ->withCount([
                                                'tracks',
                                                'tracks as artist_tracks_count' => fn (Builder $track) => $track->where('artist_id', $record->id),
                                            ])
                                            ->latest()
                                            ->get(),
                                    ))
                                    ->placeholder(__('artist.empty.playlists'))
                                    ->table([
                                        TableColumn::make(__('artist.playlists.title')),
                                        TableColumn::make(__('artist.playlists.artist_tracks')),
                                        TableColumn::make(__('artist.playlists.tracks')),
                                    ])
                                    ->schema([
                                        TextEntry::make('title')
                                            ->weight('medium'),
                                        TextEntry::make('artist_tracks_count')
                                            ->numeric(),
                                        TextEntry::make('tracks_count')
                                            ->numeric(),
                                    ])
                                    ->contained(false),
                            ]),
                        Tab::make(__('artist.tabs.bio'))
                            ->schema([
                                TextEntry::make('bio')
                                    ->hiddenLabel()
                                    ->placeholder('-')
                                    ->formatStateUsing(fn (string $state) => nl2br(e($state)))
                                    ->html()
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);

    }

    private static function tracks(string $name): RepeatableEntry
    {
        return RepeatableEntry::make($name)
            ->hiddenLabel()
            ->placeholder(__('artist.empty.tracks'))
            ->table([
                TableColumn::make(__('track.general.title')),
                TableColumn::make(__('track.general.status')),
                TableColumn::make(__('track.general.genre')),
                TableColumn::make(__('track.general.duration')),
                TableColumn::make(__('track.general.play_count')),
            ])
            ->schema([
                TextEntry::make('title')
                    ->weight('medium')
                    ->url(fn (Track $record): string => TrackResource::getUrl('view', ['record' => $record])),
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
            ->contained(false);
    }
}
