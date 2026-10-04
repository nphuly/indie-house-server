<?php

namespace App\Http\Controllers\Listen;

use App\Http\Controllers\Controller;
use App\Models\Media\Playlist;
use App\Models\Media\Track;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Contracts\View\View;

class PlaylistController extends Controller
{
    public function show(Playlist $playlist): View
    {
        Gate::authorize('view', $playlist);

        $playlist->load('user');
        $tracks = $playlist->tracks()
            ->publicCatalogue()
            ->with(['artist', 'albums'])
            ->orderByPivot('sort_order')
            ->get();

        return view('listen.playlists.show', compact('playlist', 'tracks'));
    }

    public function store(Request $request): RedirectResponse
    {
        $attributes = $this->validatedPlaylist($request);
        $playlist = $request->user()->playlists()->create($attributes);

        return redirect()->route('playlists.show', $playlist)->with('status', __('listen.playlist.created'));
    }

    public function update(Request $request, Playlist $playlist): RedirectResponse
    {
        Gate::authorize('update', $playlist);
        $playlist->update($this->validatedPlaylist($request));

        return back()->with('status', __('listen.playlist.saved'));
    }

    public function destroy(Playlist $playlist): RedirectResponse
    {
        Gate::authorize('delete', $playlist);
        $playlist->delete();

        return redirect()->route('library.index')->with('status', __('listen.playlist.deleted'));
    }

    public function storeTrack(Request $request, Playlist $playlist): RedirectResponse
    {
        Gate::authorize('update', $playlist);

        $attributes = $request->validate(['track_id' => ['required', 'string', 'max:64']]);
        $track = Track::query()->publicCatalogue()->where('public_id', $attributes['track_id'])->firstOrFail();

        if (! $playlist->tracks()->whereKey($track->id)->exists()) {
            $sortOrder = (int) $playlist->tracks()->max('playlist_tracks.sort_order') + 1;
            $playlist->tracks()->attach($track->id, ['sort_order' => $sortOrder, 'added_at' => now()]);
        }

        return back()->with('status', __('listen.playlist.track_added'));
    }

    public function destroyTrack(Playlist $playlist, Track $track): RedirectResponse
    {
        Gate::authorize('update', $playlist);
        $playlist->tracks()->detach($track->id);

        return back()->with('status', __('listen.playlist.track_removed'));
    }

    /** @return array<string, mixed> */
    private function validatedPlaylist(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'min:2', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_public' => ['required', 'boolean'],
        ]);
    }
}
