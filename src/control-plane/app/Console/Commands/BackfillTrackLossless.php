<?php

namespace App\Console\Commands;

use App\Enums\TrackRenditions;
use App\Models\Media\Track;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class BackfillTrackLossless extends Command
{
    protected $signature = 'tracks:backfill-lossless {--dispatch : Queue lossless-only jobs} {--limit=0 : Maximum tracks to queue}';

    protected $description = 'Queue FLAC renditions for tracks uploaded as FLAC or WAV, without reprocessing or deleting anything else';

    public function handle(): int
    {
        $limit = max(0, (int) $this->option('limit'));
        $dispatched = 0;
        // The worker checks the codec too; the extension only avoids queueing
        // jobs for MP3 uploads, which have no lossless audio to keep.
        $query = Track::query()->where('status', 'completed')
            ->whereNotNull('raw_file_key')
            ->where(fn ($query) => $query->whereRaw('lower(raw_file_key) like ?', ['%.flac'])
                ->orWhereRaw('lower(raw_file_key) like ?', ['%.wav']))
            ->whereDoesntHave('renditions', fn ($query) => $query->where('quality_label', TrackRenditions::LOSSLESS->value))
            ->orderBy('id');

        foreach ($query->cursor() as $track) {
            if ($this->option('dispatch')) {
                Redis::connection('media_worker')->lpush(config('services.media_worker.queue'), json_encode([
                    'kind' => 'lossless_backfill',
                    'track_id' => $track->id,
                    'raw_file_key' => $track->raw_file_key,
                    'attempt' => 0,
                ], JSON_THROW_ON_ERROR));
            }
            $dispatched++;
            if ($limit > 0 && $dispatched >= $limit) {
                break;
            }
        }

        $this->info($this->option('dispatch')
            ? "Queued {$dispatched} lossless jobs."
            : "Found {$dispatched} lossless uploads without a FLAC rendition. Pass --dispatch to queue them.");

        return self::SUCCESS;
    }
}
