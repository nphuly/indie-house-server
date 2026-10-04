<?php

namespace App\Http\Controllers\Listen;

use App\Http\Controllers\Controller;
use App\Models\Media\Album;
use App\Models\Media\Track;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        $tracks = Track::query()
            ->publicCatalogue()
            ->with(['artist', 'albums'])
            ->orderByDesc('play_count')
            ->latest()
            ->take(8)
            ->get();

        $albums = Album::query()
            ->whereHas('tracks', fn ($query) => $query->publicCatalogue())
            ->with(['artist', 'tracks' => fn ($query) => $query->publicCatalogue()])
            ->latest()
            ->take(6)
            ->get();

        $recentTracks = collect();

        if (auth()->check()) {
            $recentTracks = auth()->user()->playLogs()
                ->with(['track.artist', 'track.albums'])
                ->latest('created_at')
                ->take(32)
                ->get()
                ->pluck('track')
                ->filter(fn (?Track $track) => $track?->is_published && $track->status === 'completed')
                ->unique('id')
                ->take(8)
                ->values();
        }

        return view('listen.home', compact('albums', 'tracks', 'recentTracks'));
    }
}
