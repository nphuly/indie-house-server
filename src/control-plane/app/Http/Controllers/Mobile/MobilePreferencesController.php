<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Services\Media\PlaybackRenditions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MobilePreferencesController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->data($request)]);
    }

    public function update(Request $request, PlaybackRenditions $renditions): JsonResponse
    {
        $attributes = $request->validate([
            'audio_quality' => ['sometimes', Rule::in($renditions->choicesFor($request->user()))],
            'autoplay' => ['sometimes', 'boolean'],
            'record_listening_history' => ['sometimes', 'boolean'],
        ]);

        $request->user()->preference()->updateOrCreate([], $attributes);
        $request->user()->unsetRelation('preference');

        return response()->json(['data' => $this->data($request)]);
    }

    private function data(Request $request): array
    {
        $preferences = $request->user()->preference;

        return [
            'audio_quality' => $preferences?->audio_quality ?? 'auto',
            // Apps show the Lossless choice only when an admin granted it.
            'lossless_available' => in_array('lossless', app(PlaybackRenditions::class)->choicesFor($request->user()), true),
            'autoplay' => $preferences?->autoplay ?? true,
            'record_listening_history' => $preferences?->record_listening_history ?? true,
        ];
    }
}
