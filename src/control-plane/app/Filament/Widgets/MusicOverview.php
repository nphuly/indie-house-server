<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Albums\AlbumsResource;
use App\Filament\Resources\Artists\ArtistResource;
use App\Filament\Resources\Tracks\TrackResource;
use App\Models\Media\Album;
use App\Models\Media\Artist;
use App\Models\Media\Track;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MusicOverview extends StatsOverviewWidget
{
    public static function canView(): bool
    {
        return ArtistResource::canViewAny() || AlbumsResource::canViewAny() || TrackResource::canViewAny();
    }

    protected function getHeading(): ?string
    {
        return __('dashboard.library_heading');
    }

    protected function getDescription(): ?string
    {
        return __('dashboard.library_description');
    }

    protected function getStats(): array
    {
        $stats = [];

        if (ArtistResource::canViewAny()) {
            $stats[] = Stat::make(__('dashboard.artists'), Artist::query()->count())
                ->description(__('dashboard.view_artists'))
                ->url(ArtistResource::getUrl());
        }

        if (AlbumsResource::canViewAny()) {
            $stats[] = Stat::make(__('dashboard.albums'), Album::query()->count())
                ->description(__('dashboard.view_albums'))
                ->url(AlbumsResource::getUrl());
        }

        if (TrackResource::canViewAny()) {
            $stats[] = Stat::make(__('dashboard.tracks'), Track::query()->count())
                ->description(__('dashboard.view_tracks'))
                ->url(TrackResource::getUrl());
        }

        return $stats;
    }
}
