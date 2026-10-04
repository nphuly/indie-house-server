<?php

namespace App\Http\Controllers\Listen;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class LibraryController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        $playlists = $user->playlists()
            ->withCount('tracks')
            // The first public tracks give each card up to four distinct covers
            // for its artwork mosaic.
            ->with(['tracks' => fn ($query) => $query->publicCatalogue()->with(['albums', 'artist'])->orderByPivot('sort_order')->limit(12)])
            ->latest()
            ->get();

        $history = $user->playLogs()
            ->with(['track.artist', 'track.albums'])
            ->latest('created_at')
            // Fetch a little more before collapsing repeat log entries. This
            // preserves existing raw logs while the library shows one row per
            // track, ordered by the latest listen.
            ->take(120)
            ->get()
            ->filter(fn ($log) => $log->track?->is_published && $log->track->status === 'completed')
            ->unique('track_id')
            ->take(30)
            ->values();

        return view('listen.library.index', compact('playlists', 'history'));
    }
}
