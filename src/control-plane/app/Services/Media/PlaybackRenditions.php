<?php

namespace App\Services\Media;

use App\Enums\TrackFormats;
use App\Enums\TrackRenditions;
use App\Models\Auth\User;
use App\Models\Media\Track;
use App\Models\Media\TrackRendition;
use App\Services\Auth\StreamingAccessService;

/**
 * The one place that decides which stored file answers a playback request:
 * the gateway's asset lookup and the web routes that redirect to it both ask
 * here. `preview` is the 30-second guest preview. The other qualities prefer
 * one file and fall back to the next best: lossless to 320 to 128.
 */
class PlaybackRenditions
{
    public const QUALITIES = ['auto', 'standard', 'high', 'lossless', 'preview'];

    public function __construct(private readonly StreamingAccessService $access) {}

    public function audio(Track $track, string $quality): ?TrackRendition
    {
        if ($quality === 'lossless') {
            // A lossy upload has no FLAC; the best MP3 plays instead.
            return $track->renditions()
                ->where('format', TrackFormats::FLAC->value)
                ->where('quality_label', TrackRenditions::LOSSLESS->value)
                ->first() ?? $this->audio($track, 'high');
        }

        $renditions = $track->renditions()->where('format', TrackFormats::MP3->value);

        if ($quality === 'preview') {
            return $renditions->where('quality_label', TrackRenditions::PREVIEW_30->value)->first();
        }

        $labels = $quality === 'high'
            ? [TrackRenditions::MP3_320->value, TrackRenditions::MP3_128->value]
            : [TrackRenditions::MP3_128->value, TrackRenditions::MP3_320->value];

        return $renditions->whereIn('quality_label', $labels)
            ->orderByRaw("case quality_label when '{$labels[0]}' then 0 else 1 end")
            ->first();
    }

    public static function contentType(TrackRendition $rendition): string
    {
        return $rendition->format === TrackFormats::FLAC->value ? 'audio/flac' : 'audio/mpeg';
    }

    /** The qualities a listener may save or ask for. Lossless needs an admin's grant. */
    public function choicesFor(User $user): array
    {
        return $this->access->forUser($user)['lossless_enabled']
            ? ['auto', 'standard', 'high', 'lossless']
            : ['auto', 'standard', 'high'];
    }

    /** A saved lossless preference outlives a revoked grant; it then plays the best MP3. */
    public function allowedQuality(User $user, string $quality): string
    {
        return $quality === 'lossless' && ! in_array('lossless', $this->choicesFor($user), true) ? 'high' : $quality;
    }
}
