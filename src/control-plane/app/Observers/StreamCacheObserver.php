<?php

namespace App\Observers;

use App\Models\Media\Track;
use App\Models\Media\TrackRendition;
use App\Services\Media\StreamCacheInvalidator;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/**
 * Tells the stream gateway to forget a track when what it may serve changes:
 * publishing, processing status, deletion or its files. It runs after the
 * transaction commits, so the gateway never re-reads the old state.
 */
class StreamCacheObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(private readonly StreamCacheInvalidator $invalidator) {}

    public function saved(Track|TrackRendition $model): void
    {
        if ($model instanceof Track && ! $model->wasChanged(['is_published', 'status', 'public_id'])) {
            return;
        }

        $this->forget($model);
    }

    public function deleted(Track|TrackRendition $model): void
    {
        $this->forget($model);
    }

    private function forget(Track|TrackRendition $model): void
    {
        $publicId = $model instanceof Track
            ? $model->public_id
            : Track::query()->whereKey($model->track_id)->value('public_id');

        if (is_string($publicId) && $publicId !== '') {
            $this->invalidator->forget($publicId);
        }
    }
}
