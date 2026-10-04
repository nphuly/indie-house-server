<?php

namespace App\Console\Commands;

use App\Enums\TrackRenditions;
use App\Models\Media\Track;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class BackfillTrackPreviews extends Command
{
    protected $signature = 'tracks:backfill-previews {--dispatch : Queue preview-only jobs} {--limit=0 : Maximum tracks to queue}';

    protected $description = 'Queue missing guest previews without reprocessing or deleting full renditions';

    public function handle(): int
    {
        $limit = max(0, (int) $this->option('limit'));
        $dispatched = 0;
        $query = Track::query()->where('status', 'completed')
            ->whereDoesntHave('renditions', fn ($query) => $query->where('quality_label', TrackRenditions::PREVIEW_30->value))
            ->whereHas('renditions', fn ($query) => $query->where('quality_label', TrackRenditions::MP3_128->value))
            ->orderBy('id');

        foreach ($query->cursor() as $track) {
            $source = $track->renditions()->where('quality_label', TrackRenditions::MP3_128->value)->value('file_key');
            if (! $source) {
                continue;
            }
            if ($this->option('dispatch')) {
                Redis::connection('media_worker')->lpush(config('services.media_worker.queue'), json_encode([
                    'kind' => 'preview_backfill',
                    'track_id' => $track->id,
                    'raw_file_key' => $source,
                    'attempt' => 0,
                ], JSON_THROW_ON_ERROR));
            }
            $dispatched++;
            if ($limit > 0 && $dispatched >= $limit) {
                break;
            }
        }

        $this->info($this->option('dispatch')
            ? "Queued {$dispatched} preview jobs."
            : "Found {$dispatched} tracks needing a preview. Pass --dispatch to queue them.");

        return self::SUCCESS;
    }
}
