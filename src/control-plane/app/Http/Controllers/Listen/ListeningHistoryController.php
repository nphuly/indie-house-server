<?php

namespace App\Http\Controllers\Listen;

use App\Http\Controllers\Controller;
use App\Models\Media\Track;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ListeningHistoryController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        if ($request->user()->preference?->record_listening_history === false) {
            return response()->json(status: 204);
        }

        $attributes = $request->validate([
            'track_id' => ['required', 'string', 'max:64'],
            'played_duration_seconds' => ['required', 'integer', 'min:1'],
            'completed' => ['required', 'boolean'],
            'playback_id' => ['required', 'string', 'max:64'],
        ]);

        $track = Track::query()
            ->publicCatalogue()
            ->where('public_id', $attributes['track_id'])
            ->firstOrFail();

        // A browser may retry a keepalive request while navigating away.  A
        // short-lived id generated for each playback makes that retry harmless
        // without requiring a schema change or modifying historical records.
        $deduplicationKey = 'listening-history:'.hash('sha256', implode('|', [
            $request->user()->getAuthIdentifier(),
            $track->id,
            $attributes['playback_id'],
        ]));

        if (! Cache::add($deduplicationKey, true, now()->addHours(2))) {
            return response()->json(status: 204);
        }

        // A play log keeps what the listener's history needs, not their
        // network address or browser (see docs/en/privacy.md).
        DB::transaction(function () use ($request, $track, $attributes): void {
            $request->user()->playLogs()->create([
                'track_id' => $track->id,
                'played_duration_seconds' => min($attributes['played_duration_seconds'], $track->duration_seconds),
                'completed' => $attributes['completed'],
            ]);

            if ($attributes['completed']) {
                Track::query()->whereKey($track->id)->increment('play_count');
            }
        });

        return response()->json(status: 201);
    }
}
