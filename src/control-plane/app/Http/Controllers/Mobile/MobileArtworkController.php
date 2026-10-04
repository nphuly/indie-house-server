<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Media\Album;
use App\Models\Media\Artist;
use App\Models\Media\Track;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MobileArtworkController extends Controller
{
    public function __invoke(Track $track): StreamedResponse
    {
        abort_unless($track->is_published && $track->status === 'completed', 404);
        $album = $track->albums()->whereNotNull('cover_image')->first();
        $path = $album?->cover_image ?: ($track->artist?->avatar ?: $track->artist?->cover_image);
        return $this->stream($path);
    }

    public function artist(Artist $artist): StreamedResponse
    {
        abort_unless($artist->tracks()->publicCatalogue()->exists(), 404);
        return $this->stream($artist->avatar ?: $artist->cover_image);
    }

    public function album(Album $album): StreamedResponse
    {
        abort_unless($album->tracks()->publicCatalogue()->exists(), 404);
        return $this->stream($album->cover_image);
    }

    /** The signed-in user's own profile picture; the version in the path only busts caches. */
    public function avatar(Request $request): StreamedResponse
    {
        return $this->stream($request->user()->avatar_path);
    }

    private function stream(?string $path): StreamedResponse
    {
        abort_unless(is_string($path) && $path !== '', 404);

        $disk = Storage::disk('s3');
        abort_unless($disk->exists($path), 404);
        $mime = $disk->mimeType($path);
        abort_unless(in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true), 404);

        return response()->stream(function () use ($disk, $path): void {
            $stream = $disk->readStream($path);
            if (is_resource($stream)) {
                fpassthru($stream);
                fclose($stream);
            }
        }, 200, ['Content-Type' => $mime, 'Cache-Control' => 'private, max-age=300', 'X-Content-Type-Options' => 'nosniff']);
    }
}
