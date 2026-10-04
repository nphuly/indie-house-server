<?php

namespace App\Http\Controllers\Listen;

use App\Enums\TrackRenditions;
use App\Http\Controllers\Controller;
use App\Models\Media\Track;
use App\Services\Media\PlaybackRenditions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PlaybackSourceController extends Controller
{
    public function __invoke(Request $request, Track $track, PlaybackRenditions $renditions): JsonResponse
    {
        abort_unless($track->is_published && $track->status === 'completed', 404);

        $demo = $track->renditions()->where('file_key', 'like', 'catalogue-demo/%')->first();
        if ($demo && Storage::disk('public')->exists($demo->file_key)) {
            return response()->json(['source' => route('tracks.audio', $track), 'quality' => 'demo']);
        }

        if (! $request->user()) {
            abort_unless($renditions->audio($track, 'preview'), 404);

            return response()->json(['source' => route('tracks.audio', $track), 'quality' => TrackRenditions::PREVIEW_30->value]);
        }

        $quality = $request->user()->preference?->audio_quality ?? 'auto';

        return response()->json(['source' => route('tracks.audio', [$track, 'quality' => $quality]), 'quality' => $quality]);
    }
}
