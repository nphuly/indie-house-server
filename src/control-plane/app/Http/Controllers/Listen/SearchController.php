<?php

namespace App\Http\Controllers\Listen;

use App\Http\Controllers\Controller;
use App\Models\Media\Album;
use App\Models\Media\Artist;
use App\Models\Media\Track;
use App\Support\PlayerTrack;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SearchController extends Controller
{
    public function __invoke(Request $request): View|JsonResponse
    {
        $query = trim((string) $request->query('q', ''));
        $suggest = $request->boolean('suggest');
        $tracks = collect();
        $artists = collect();
        $albums = collect();

        if ($query !== '') {
            $terms = $this->searchTerms($query);

            $tracks = Track::query()
                ->publicCatalogue()
                ->with(['artist', 'albums'])
                ->where(fn (Builder $builder) => $this->matchTrackTerms($builder, $terms))
                ->orderByDesc('play_count')
                ->take($suggest ? 5 : 20)
                ->get();

            $artists = Artist::query()
                ->where(fn (Builder $builder) => $this->matchTerms($builder, 'name', $terms))
                ->whereHas('tracks', fn (Builder $track) => $track->publicCatalogue())
                ->take($suggest ? 3 : 12)
                ->get();

            $albums = Album::query()
                ->whereHas('tracks', fn (Builder $track) => $track->publicCatalogue())
                ->with('artist')
                ->where(function (Builder $builder) use ($terms): void {
                    foreach ($terms as $term) {
                        $builder->where(function (Builder $match) use ($term): void {
                            $this->matchTerm($match, 'title', $term)
                                ->orWhereHas('artist', fn (Builder $artist) => $this->matchTerm($artist, 'name', $term));
                        });
                    }
                })
                ->take($suggest ? 3 : 12)
                ->get();
        }

        if ($suggest) {
            return response()->json([
                'tracks' => PlayerTrack::queue($tracks),
                'artists' => $artists->map(fn (Artist $artist): array => [
                    'title' => $artist->name,
                    'artwork' => $artist->avatar_url ?? $artist->cover_url,
                    'url' => route('artists.show', $artist),
                ])->values(),
                'albums' => $albums->map(fn (Album $album): array => [
                    'title' => $album->title,
                    'artist' => $album->artist->name,
                    'artwork' => $album->cover_url,
                    'url' => route('albums.show', $album),
                ])->values(),
            ]);
        }

        // An empty search page offers genres to browse instead of a blank form.
        $genres = $query === ''
            ? Track::query()
                ->publicCatalogue()
                ->whereNotNull('genre')
                ->where('genre', '!=', '')
                ->selectRaw('genre, count(*) as tracks_count')
                ->groupBy('genre')
                ->orderByDesc('tracks_count')
                ->orderBy('genre')
                ->take(24)
                ->get()
            : collect();

        return view('listen.search', compact('query', 'tracks', 'artists', 'albums', 'genres'));
    }

    /** @return array<int, string> */
    private function searchTerms(string $query): array
    {
        return collect(preg_split('/\s+/u', Str::ascii($query)) ?: [])
            ->map(fn (string $term): string => Str::lower(trim($term)))
            ->filter(fn (string $term): bool => filled($term))
            ->unique()
            ->take(5)
            ->values()
            ->all();
    }

    /** @param array<int, string> $terms */
    private function matchTrackTerms(Builder $builder, array $terms): void
    {
        foreach ($terms as $term) {
            $builder->where(function (Builder $match) use ($term): void {
                $this->matchTerm($match, 'title', $term)
                    ->orWhere(fn (Builder $genre) => $this->matchTerm($genre, 'genre', $term))
                    ->orWhereHas('artist', fn (Builder $artist) => $this->matchTerm($artist, 'name', $term));
            });
        }
    }

    /** @param array<int, string> $terms */
    private function matchTerms(Builder $builder, string $column, array $terms): void
    {
        foreach ($terms as $term) {
            $this->matchTerm($builder, $column, $term);
        }
    }

    private function matchTerm(Builder $builder, string $column, string $term): Builder
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
