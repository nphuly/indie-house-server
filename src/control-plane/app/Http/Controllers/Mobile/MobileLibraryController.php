<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Media\Playlist;
use App\Models\Media\Track;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class MobileLibraryController extends Controller
{
    public function __construct(private readonly MobileCatalogueController $catalogue) {}

    public function index(Request $request): JsonResponse
    {
        $playlists = $request->user()->playlists()->withCount('tracks')->latest()->get();
        $history = $request->user()->playLogs()->whereHas('track', fn ($query) => $query->publicCatalogue())
            ->with(['track.artist', 'track.albums'])->latest('created_at')->take(120)->get()
            ->unique('track_id')->take(30)->values();

        return response()->json(['data' => [
            'playlists' => $playlists->map(fn (Playlist $playlist): array => $this->playlistData($playlist)),
            'history' => $history->map(fn ($log): array => [
                'track' => $this->catalogue->trackData($log->track),
                'played_duration_seconds' => $log->played_duration_seconds,
                'completed' => $log->completed,
                'created_at' => $log->created_at?->toIso8601String(),
            ]),
        ]]);
    }

    public function playlists(Request $request): JsonResponse
    {
        $playlists = $request->user()->playlists()->withCount('tracks')->latest()->get();

        return response()->json(['data' => $playlists->map(fn (Playlist $playlist): array => $this->playlistData($playlist))]);
    }

    public function playlist(Request $request, Playlist $playlist): JsonResponse
    {
        Gate::forUser($request->user())->authorize('view', $playlist);
        $tracks = $playlist->tracks()->publicCatalogue()->with(['artist', 'albums'])->get();

        return response()->json(['data' => [
            ...$this->playlistData($playlist),
            'tracks' => $tracks->map(fn (Track $track): array => $this->catalogue->trackData($track)),
        ]]);
    }

    public function storePlaylist(Request $request): JsonResponse
    {
        $attributes = $request->validate($this->playlistRules(true));
        $playlist = $request->user()->playlists()->create($attributes);

        return response()->json(['data' => $this->playlistData($playlist)], 201);
    }

    public function updatePlaylist(Request $request, Playlist $playlist): JsonResponse
    {
        Gate::forUser($request->user())->authorize('update', $playlist);
        $playlist->update($request->validate($this->playlistRules(false)));

        return response()->json(['data' => $this->playlistData($playlist)]);
    }

    public function deletePlaylist(Request $request, Playlist $playlist): JsonResponse
    {
        Gate::forUser($request->user())->authorize('delete', $playlist);
        $playlist->delete();

        return response()->json(status: 204);
    }

    public function addTrack(Request $request, Playlist $playlist): JsonResponse
    {
        Gate::forUser($request->user())->authorize('update', $playlist);
        $attributes = $request->validate(['track_id' => ['required', 'string', 'max:64']]);
        $track = Track::query()->publicCatalogue()->where('public_id', $attributes['track_id'])->firstOrFail();

        DB::transaction(function () use ($playlist, $track): void {
            $locked = Playlist::query()->whereKey($playlist->getKey())->lockForUpdate()->firstOrFail();
            if (! $locked->tracks()->whereKey($track->getKey())->exists()) {
                $sortOrder = (int) $locked->tracks()->max('playlist_tracks.sort_order') + 1;
                $locked->tracks()->attach($track->getKey(), ['sort_order' => $sortOrder, 'added_at' => now()]);
            }
        });

        return response()->json(status: 204);
    }

    public function removeTrack(Request $request, Playlist $playlist, Track $track): JsonResponse
    {
        Gate::forUser($request->user())->authorize('update', $playlist);
        $playlist->tracks()->detach($track->getKey());

        return response()->json(status: 204);
    }

    public function storePlayLog(Request $request): JsonResponse
    {
        if ($request->user()->preference?->record_listening_history === false) {
            return response()->json(status: 204);
        }

        $attributes = $request->validate([
            'track_id' => ['required', 'string', 'max:64'],
            'played_duration_seconds' => ['required', 'integer', 'min:1'],
            'completed' => ['required', 'boolean'],
            'playback_id' => ['required', 'string', 'max:64'],
        ]);
        $track = Track::query()->publicCatalogue()->where('public_id', $attributes['track_id'])->firstOrFail();
        $key = 'listening-history:'.hash('sha256', implode('|', [
            $request->user()->getAuthIdentifier(), $track->getKey(), $attributes['playback_id'],
        ]));

        if (! Cache::add($key, true, now()->addHours(2))) {
            return response()->json(status: 204);
        }

        // Like the web, no network address or user agent in play logs.
        DB::transaction(function () use ($request, $track, $attributes): void {
            $request->user()->playLogs()->create([
                'track_id' => $track->getKey(),
                'played_duration_seconds' => min($attributes['played_duration_seconds'], $track->duration_seconds),
                'completed' => $attributes['completed'],
            ]);
            if ($attributes['completed']) {
                Track::query()->whereKey($track->getKey())->increment('play_count');
            }
        });

        return response()->json(status: 201);
    }

    /** @return array<string, mixed> */
    private function playlistData(Playlist $playlist): array
    {
        return [
            'id' => $playlist->public_id,
            'title' => $playlist->title,
            'description' => $playlist->description,
            'is_public' => $playlist->is_public,
            'tracks_count' => $playlist->tracks_count ?? $playlist->tracks()->count(),
        ];
    }

    /** @return array<string, array<int, string>> */
    private function playlistRules(bool $required): array
    {
        $presence = $required ? 'required' : 'sometimes';

        return [
            'title' => [$presence, 'string', 'min:2', 'max:120'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'is_public' => [$presence, 'boolean'],
        ];
    }
}
