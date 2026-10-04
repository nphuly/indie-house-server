<?php

namespace App\Jobs;

use App\Models\Media\Track;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Redis;


class ProcessTrackJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $trackId)
    {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $track = Track::findOrFail($this->trackId);

        $track->update([
            'status' => 'pending',
            'last_error_message' => null,
        ]);

        // Push to an unprefixed Redis list consumed directly by the Go media-worker.
        Redis::connection('media_worker')->lpush(config('services.media_worker.queue'), json_encode([
            'track_id' => $this->trackId,
            'raw_file_key' => $track->raw_file_key,
            'title' => $track->title,
            'artist_id' => $track->artist_id,
            'attempt' => 0,
        ]));
    }
}
