<?php

namespace App\Http\Controllers\Listen;

use App\Http\Controllers\Controller;
use App\Models\Media\Album;
use Illuminate\Contracts\View\View;

class AlbumController extends Controller
{
    public function __invoke(Album $album): View
    {
        $album->load('artist');

        $tracks = $album->tracks()
            ->publicCatalogue()
            ->with(['artist', 'albums'])
            ->get();

        abort_unless($tracks->isNotEmpty(), 404);

        return view('listen.albums.show', compact('album', 'tracks'));
    }
}
