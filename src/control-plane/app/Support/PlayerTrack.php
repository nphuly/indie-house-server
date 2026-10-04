<?php

namespace App\Support;

use App\Models\Media\Album;
use App\Models\Media\Track;
use Illuminate\Support\Collection;

/**
 * The track payload the web player reads from `data-track` attributes and
 * search suggestions. It only carries same-origin URLs: the audio itself is
 * always resolved through `tracks.audio`, never a storage URL.
 */
class PlayerTrack
{
    /**
     * Pass `$album` when the track was loaded through that album, so the
     * payload reuses its cover instead of querying the track's albums again.
     *
     * @return array<string, mixed>
     */
    public static function make(Track $track, ?Album $album = null): array
    {
        $album ??= $track->relationLoaded('albums') ? $track->albums->first() : null;
        $artist = $album?->relationLoaded('artist') && (int) $album->artist_id === (int) $track->artist_id
            ? $album->artist
            : $track->artist;

        return [
            'id' => $track->public_id,
            'title' => $track->title,
            'artist' => $artist->name,
            'artwork' => $album ? ($album->cover_url ?? $artist->avatar_url) : $track->artwork_url,
            'sourceUrl' => route('tracks.audio', $track),
            'duration' => $track->duration_seconds,
            'artistUrl' => route('artists.show', $artist),
            'album' => $album?->title,
            'albumUrl' => $album ? route('albums.show', $album) : null,
        ];
    }

    /**
     * @param  iterable<Track>  $tracks
     * @return Collection<int, array<string, mixed>>
     */
    public static function queue(iterable $tracks, ?Album $album = null): Collection
    {
        return collect($tracks)->map(fn (Track $track): array => static::make($track, $album))->values();
    }

    /** JSON that is safe inside a single-quoted HTML attribute. */
    public static function json(mixed $payload): string
    {
        return json_encode($payload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?: 'null';
    }
}
