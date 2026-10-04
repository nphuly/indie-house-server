<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Media\Album;
use App\Models\Media\Artist;
use App\Models\Media\Track;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MobileCatalogueController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tracks = Track::query()->publicCatalogue()->with(['artist', 'albums'])
            ->orderByDesc('play_count')->latest()->paginate(20);

        return response()->json([
            'data' => $tracks->getCollection()->map(fn (Track $track): array => $this->trackData($track)),
            'meta' => ['current_page' => $tracks->currentPage(), 'last_page' => $tracks->lastPage(), 'total' => $tracks->total()],
        ]);
    }

    /** Khám phá: the newest songs and albums and the most played artists, never the whole catalogue. */
    public function browse(): JsonResponse
    {
        $tracks = Track::query()->publicCatalogue()->with(['artist', 'albums'])->latest()->orderByDesc('id')->take(20)->get();
        $albums = $this->albumList()->take(12)->get();
        $artists = $this->artistList()
            ->withSum(['tracks as plays' => fn (Builder $track) => $track->publicCatalogue()], 'play_count')
            ->orderByDesc('plays')->orderBy('name')->take(12)->get();

        return response()->json(['data' => [
            'counts' => [
                'tracks' => Track::query()->publicCatalogue()->count(),
                'albums' => Album::query()->whereHas('tracks', fn (Builder $track) => $track->publicCatalogue())->count(),
                'artists' => Artist::query()->whereHas('tracks', fn (Builder $track) => $track->publicCatalogue())->count(),
            ],
            'new_tracks' => $tracks->map(fn (Track $track): array => $this->trackData($track)),
            'albums' => $albums->map(fn (Album $album): array => $this->albumCard($album)),
            'artists' => $artists->map(fn (Artist $artist): array => $this->artistCard($artist)),
        ]]);
    }

    /** Every album with a public song, newest first, 24 a page. */
    public function albums(): JsonResponse
    {
        $albums = $this->albumList()->paginate(24);

        return response()->json([
            'data' => $albums->getCollection()->map(fn (Album $album): array => $this->albumCard($album)),
            'meta' => ['current_page' => $albums->currentPage(), 'last_page' => $albums->lastPage(), 'total' => $albums->total()],
        ]);
    }

    /** Every artist with a public song, A to Z, 24 a page. */
    public function artists(): JsonResponse
    {
        $artists = $this->artistList()->orderBy('name')->orderBy('id')->paginate(24);

        return response()->json([
            'data' => $artists->getCollection()->map(fn (Artist $artist): array => $this->artistCard($artist)),
            'meta' => ['current_page' => $artists->currentPage(), 'last_page' => $artists->lastPage(), 'total' => $artists->total()],
        ]);
    }

    public function track(Track $track): JsonResponse
    {
        abort_unless($track->is_published && $track->status === 'completed', 404);

        return response()->json(['data' => $this->trackData($track->load(['artist', 'albums']))]);
    }

    public function artist(Artist $artist): JsonResponse
    {
        $tracks = $artist->tracks()->publicCatalogue()->with(['artist', 'albums'])->get();
        abort_if($tracks->isEmpty(), 404);

        return response()->json(['data' => [
            'id' => $artist->public_id,
            'name' => $artist->name,
            'avatar_url' => ($artist->avatar ?: $artist->cover_image) ? url("/api/v1/mobile/artists/{$artist->public_id}/artwork") : null,
            'bio' => $artist->bio,
            'artwork_url' => ($artist->avatar ?: $artist->cover_image) ? url("/api/v1/mobile/artists/{$artist->public_id}/artwork") : null,
            'tracks' => $tracks->map(fn (Track $track): array => $this->trackData($track)),
        ]]);
    }

    public function album(Album $album): JsonResponse
    {
        $tracks = $album->tracks()->publicCatalogue()->with(['artist', 'albums'])->get();
        abort_if($tracks->isEmpty(), 404);

        return response()->json(['data' => [
            'id' => $album->public_id,
            'title' => $album->title,
            'description' => $album->description,
            'artwork_url' => $album->cover_image ? url("/api/v1/mobile/albums/{$album->public_id}/artwork") : null,
            'artist' => ['id' => $album->artist->public_id, 'name' => $album->artist->name],
            'tracks' => $tracks->map(fn (Track $track): array => $this->trackData($track)),
        ]]);
    }

    public function search(Request $request): JsonResponse
    {
        $query = trim((string) $request->validate(['q' => ['required', 'string', 'max:120']])['q']);
        if ($query === '') {
            return response()->json(['data' => ['tracks' => [], 'artists' => [], 'albums' => []]]);
        }

        $terms = collect(preg_split('/\s+/u', Str::ascii($query)) ?: [])
            ->map(fn (string $term): string => Str::lower(trim($term)))->filter()->unique()->take(5)->all();

        $tracks = Track::query()->publicCatalogue()->with(['artist', 'albums'])
            ->where(function (Builder $builder) use ($terms): void {
                foreach ($terms as $term) {
                    $builder->where(function (Builder $match) use ($term): void {
                        $this->match($match, 'title', $term)
                            ->orWhere(fn (Builder $genre) => $this->match($genre, 'genre', $term))
                            ->orWhereHas('artist', fn (Builder $artist) => $this->match($artist, 'name', $term));
                    });
                }
            })->orderByDesc('play_count')->take(20)->get();

        $artists = Artist::query()->whereHas('tracks', fn (Builder $track) => $track->publicCatalogue())
            ->where(fn (Builder $builder) => $this->matchAll($builder, 'name', $terms))->take(12)->get();
        $albums = Album::query()->whereHas('tracks', fn (Builder $track) => $track->publicCatalogue())
            ->with('artist')->where(function (Builder $builder) use ($terms): void {
                foreach ($terms as $term) {
                    $builder->where(function (Builder $match) use ($term): void {
                        $this->match($match, 'title', $term)
                            ->orWhereHas('artist', fn (Builder $artist) => $this->match($artist, 'name', $term));
                    });
                }
            })->take(12)->get();

        return response()->json(['data' => [
            'tracks' => $tracks->map(fn (Track $track): array => $this->trackData($track)),
            'artists' => $artists->map(fn (Artist $artist): array => [
                'id' => $artist->public_id, 'name' => $artist->name,
                'avatar_url' => ($artist->avatar ?: $artist->cover_image) ? url("/api/v1/mobile/artists/{$artist->public_id}/artwork") : null,
                'artwork_url' => ($artist->avatar ?: $artist->cover_image) ? url("/api/v1/mobile/artists/{$artist->public_id}/artwork") : null,
            ]),
            'albums' => $albums->map(fn (Album $album): array => [
                'id' => $album->public_id, 'title' => $album->title,
                'artwork_url' => $album->cover_image ? url("/api/v1/mobile/albums/{$album->public_id}/artwork") : null,
                'artist' => ['id' => $album->artist->public_id, 'name' => $album->artist->name],
            ]),
        ]]);
    }

    /** @return array<string, mixed> */
    public function trackData(Track $track): array
    {
        return [
            'id' => $track->public_id,
            'title' => $track->title,
            'duration_seconds' => $track->duration_seconds,
            'genre' => $track->genre,
            'artist' => $track->artist ? ['id' => $track->artist->public_id, 'name' => $track->artist->name] : null,
            'albums' => $track->albums->map(fn (Album $album): array => ['id' => $album->public_id, 'title' => $album->title]),
            'artwork_url' => url("/api/v1/mobile/tracks/{$track->public_id}/artwork"),
            'stream_path' => "/api/v1/stream/v1/tracks/{$track->public_id}/stream",
        ];
    }

    private function albumList(): Builder
    {
        return Album::query()->whereHas('tracks', fn (Builder $track) => $track->publicCatalogue())
            ->with('artist')
            ->withCount(['tracks as public_tracks_count' => fn (Builder $track) => $track->publicCatalogue()])
            ->latest()->orderByDesc('id');
    }

    private function artistList(): Builder
    {
        return Artist::query()->whereHas('tracks', fn (Builder $track) => $track->publicCatalogue())
            ->withCount(['tracks as public_tracks_count' => fn (Builder $track) => $track->publicCatalogue()]);
    }

    /** @return array<string, mixed> */
    private function albumCard(Album $album): array
    {
        return [
            'id' => $album->public_id,
            'title' => $album->title,
            'artwork_url' => $album->cover_image ? url("/api/v1/mobile/albums/{$album->public_id}/artwork") : null,
            'artist' => $album->artist ? ['id' => $album->artist->public_id, 'name' => $album->artist->name] : null,
            'track_count' => (int) $album->public_tracks_count,
        ];
    }

    /** @return array<string, mixed> */
    private function artistCard(Artist $artist): array
    {
        $artwork = ($artist->avatar ?: $artist->cover_image) ? url("/api/v1/mobile/artists/{$artist->public_id}/artwork") : null;

        return [
            'id' => $artist->public_id,
            'name' => $artist->name,
            'avatar_url' => $artwork,
            'artwork_url' => $artwork,
            'track_count' => (int) $artist->public_tracks_count,
        ];
    }

    /** @param array<int, string> $terms */
    private function matchAll(Builder $builder, string $column, array $terms): void
    {
        foreach ($terms as $term) {
            $this->match($builder, $column, $term);
        }
    }

    private function match(Builder $builder, string $column, string $term): Builder
    {
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';

        return $builder->where(function (Builder $match) use ($builder, $column, $like, $term): void {
            if ($builder->getConnection()->getDriverName() !== 'pgsql') {
                $match->whereLike($column, $like);

                return;
            }
            $match->whereRaw("immutable_unaccent(lower(coalesce({$column}, ''))) LIKE ? ESCAPE '\\'", [$like]);
            if (mb_strlen($term) >= 4) {
                $match->orWhereRaw("similarity(immutable_unaccent(lower(coalesce({$column}, ''))), ?) >= 0.2", [$term]);
            }
        });
    }
}
