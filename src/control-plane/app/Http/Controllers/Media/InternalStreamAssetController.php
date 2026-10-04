<?php

namespace App\Http\Controllers\Media;

use App\Enums\TrackFormats;
use App\Enums\TrackRenditions;
use App\Http\Controllers\Controller;
use App\Models\Media\Track;
use App\Services\Media\PlaybackRenditions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Private catalogue lookup for the stream gateway.
 *
 * This route deliberately returns an object key only to callers that passed
 * internal.api; it must never be exposed through the browser/mobile routes.
 */
class InternalStreamAssetController extends Controller
{
    public function __invoke(Request $request, Track $track, PlaybackRenditions $renditions): JsonResponse
    {
        $input = $request->validate([
            'kind' => ['required', 'in:audio,hls'],
            'quality' => ['nullable', Rule::in(PlaybackRenditions::QUALITIES)],
            'asset' => ['nullable', 'string', 'max:128'],
        ]);

        abort_unless($track->is_published && $track->status === 'completed', 404);

        if ($input['kind'] === 'audio') {
            $rendition = $renditions->audio($track, $input['quality'] ?? 'auto');
            abort_unless($rendition, 404);

            return response()->json([
                'key' => $rendition->file_key,
                'content_type' => PlaybackRenditions::contentType($rendition),
                'quality' => $rendition->quality_label,
            ]);
        }

        return $this->hlsAsset($track, $input['asset'] ?? 'master.m3u8');
    }

    private function hlsAsset(Track $track, string $asset): JsonResponse
    {
        abort_unless(
            preg_match('/^[A-Za-z0-9][A-Za-z0-9_.-]*\.(m3u8|ts|aac|m4s)$/', $asset) === 1,
            404,
        );

        $rendition = $track->renditions()
            ->where('format', TrackFormats::HLS->value)
            ->whereIn('quality_label', [TrackRenditions::HLS_128->value, TrackRenditions::HLS_320->value])
            ->first();

        abort_unless($rendition, 404);

        return response()->json([
            'key' => dirname($rendition->file_key).'/'.$asset,
            'content_type' => match (pathinfo($asset, PATHINFO_EXTENSION)) {
                'm3u8' => 'application/vnd.apple.mpegurl',
                'ts' => 'video/mp2t',
                'aac' => 'audio/aac',
                default => 'video/iso.segment',
            },
            'quality' => 'adaptive',
        ]);
    }
}
