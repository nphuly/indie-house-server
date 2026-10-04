<?php

namespace App\Http\Controllers\Media;

use App\Enums\TrackFormats;
use App\Enums\TrackRenditions;
use App\Http\Controllers\Controller;
use App\Models\Media\Track;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TrackProcessingController extends Controller
{
    public function registerPreview(Request $request)
    {
        $data = $request->validate([
            'track_id' => ['required', 'uuid', 'exists:tracks,id'],
            'source_file_key' => ['required', 'string'],
            'file_key' => ['required', 'string'],
            'size_bytes' => ['required', 'integer', 'min:1'],
        ]);
        DB::transaction(function () use ($data): void {
            $track = Track::query()->lockForUpdate()->findOrFail($data['track_id']);
            abort_unless($track->status === 'completed', 409);
            abort_unless($data['file_key'] === "processed/{$track->id}/preview_30.mp3", 422);
            abort_unless($track->renditions()->where('file_key', $data['source_file_key'])->exists()
                || $track->raw_file_key === $data['source_file_key'], 422);

            $track->renditions()->updateOrCreate(
                ['quality_label' => TrackRenditions::PREVIEW_30->value],
                ['format' => 'mp3', 'bitrate_kbps' => 96, 'file_key' => $data['file_key'], 'file_size_bytes' => $data['size_bytes']],
            );
        });

        return response()->json(['ok' => true]);
    }

    /** The media worker's lossless backfill adds the FLAC without touching the other renditions. */
    public function registerLossless(Request $request)
    {
        $data = $request->validate([
            'track_id' => ['required', 'uuid', 'exists:tracks,id'],
            'source_file_key' => ['required', 'string'],
            'file_key' => ['required', 'string'],
            'size_bytes' => ['required', 'integer', 'min:1'],
            'bitrate_kbps' => ['required', 'integer', 'min:0'],
        ]);
        DB::transaction(function () use ($data): void {
            $track = Track::query()->lockForUpdate()->findOrFail($data['track_id']);
            abort_unless($track->status === 'completed', 409);
            abort_unless($data['file_key'] === "processed/{$track->id}/lossless.flac", 422);
            abort_unless($track->raw_file_key === $data['source_file_key'], 422);

            $track->renditions()->updateOrCreate(
                ['quality_label' => TrackRenditions::LOSSLESS->value],
                ['format' => TrackFormats::FLAC->value, 'bitrate_kbps' => $data['bitrate_kbps'], 'file_key' => $data['file_key'], 'file_size_bytes' => $data['size_bytes']],
            );
        });

        return response()->json(['ok' => true]);
    }

    public function updateStatus(Request $request)
    {
        $request->validate([
            'track_id' => 'required|uuid|exists:tracks,id',
            'status' => 'required|in:pending,processing,completed,failed',
            'last_error_message' => 'nullable|string',
        ]);

        Track::findOrFail($request->track_id)->update([
            'status' => $request->status,
            'last_error_message' => $request->last_error_message,
        ]);

        return response()->json(['ok' => true]);
    }

    public function createRenditions(Request $request)
    {
        $request->merge([
            'renditions' => collect($request->input('renditions', []))->map(fn (array $rendition) => [
                'quality_label' => $rendition['quality_label'] ?? $rendition['QualityLabel'] ?? null,
                'format' => $rendition['format'] ?? $rendition['Format'] ?? null,
                'bitrate_kbps' => $rendition['bitrate_kbps'] ?? $rendition['BitrateKbps'] ?? null,
                'file_key' => $rendition['file_key'] ?? $rendition['FileKey'] ?? null,
                'size_bytes' => $rendition['size_bytes'] ?? $rendition['SizeBytes'] ?? null,
            ])->all(),
        ]);

        $request->validate([
            'track_id' => 'required|uuid|exists:tracks,id',
            'duration_seconds' => 'required|integer|min:0',
            'renditions' => 'required|array',
            'renditions.*.quality_label' => 'required|string',
            'renditions.*.format' => 'required|string',
            'renditions.*.bitrate_kbps' => 'required|integer',
            'renditions.*.file_key' => 'required|string',
            'renditions.*.size_bytes' => 'required|integer',
        ]);

        $track = Track::findOrFail($request->track_id);

        DB::transaction(function () use ($request, $track) {
            $track->update([
                'duration_seconds' => $request->duration_seconds,
                'status' => 'completed',
            ]);

            $track->renditions()->delete();

            foreach ($request->renditions as $r) {
                $track->renditions()->create([
                    'quality_label' => $r['quality_label'],
                    'format' => $r['format'],
                    'bitrate_kbps' => $r['bitrate_kbps'],
                    'file_key' => $r['file_key'],
                    'file_size_bytes' => $r['size_bytes'],
                ]);
            }
        });

        return response()->json(['ok' => true]);
    }
}
