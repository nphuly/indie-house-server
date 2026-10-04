<?php

namespace App\Http\Controllers\Listen;

use App\Http\Controllers\Controller;
use App\Models\Media\Artist;
use Illuminate\Contracts\View\View;

class ArtistController extends Controller
{
    public function __invoke(Artist $artist): View
    {
        $albums = $artist->albums()
            ->whereHas('tracks', fn ($query) => $query->publicCatalogue())
            ->with(['tracks' => fn ($query) => $query->publicCatalogue()])
            ->latest()
            ->get();

        $tracks = $artist->tracks()
            ->publicCatalogue()
            ->with('albums')
            ->latest()
            ->get();

        // Both lists belong to this artist; reuse the model instead of lazy
        // loading it once per card and row.
        $albums->each->setRelation('artist', $artist);
        $tracks->each->setRelation('artist', $artist);

        return view('listen.artists.show', compact('artist', 'albums', 'tracks'));
    }
}
