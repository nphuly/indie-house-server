<?php

namespace App\Http\Controllers\Listen;

use App\Http\Controllers\Controller;
use App\Models\Media\Album;
use App\Models\Media\Artist;
use App\Models\Media\Track;
use Illuminate\Contracts\View\View;

class CatalogueController extends Controller
{
    public function __invoke(): View
    {
        $tracks = Track::query()
            ->publicCatalogue()
            ->with(['artist', 'albums'])
            ->orderByDesc('play_count')
            ->latest()
            ->paginate(20, pageName: 'tracks');

        $artists = Artist::query()
            ->whereHas('tracks', fn ($query) => $query->publicCatalogue())
            ->withCount(['tracks as public_tracks_count' => fn ($query) => $query->publicCatalogue()])
            ->orderBy('name')
            ->get();

        // Albums carry their public tracks so each card can start playback
        // without opening the album page first.
        $albums = Album::query()
            ->whereHas('tracks', fn ($query) => $query->publicCatalogue())
            ->with(['artist', 'tracks' => fn ($query) => $query->publicCatalogue()])
            ->latest()
            ->take(24)
            ->get();

        return view('listen.catalogue', compact('albums', 'artists', 'tracks'));
    }
}
