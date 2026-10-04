<?php

namespace App\Services\Media;

use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Tells the stream gateway to drop what it cached about a track, so a change
 * to its visibility or files reaches listeners at once. The channel and the
 * payload (a track public ID, or "*") are a contract with stream-service
 * (internal/assetcache); change both sides together.
 */
class StreamCacheInvalidator
{
    public const CHANNEL = 'stream:invalidate';

    public function forget(string $publicId): void
    {
        $this->publish($publicId);
    }

    public function forgetAll(): void
    {
        $this->publish('*');
    }

    private function publish(string $message): void
    {
        if (! config('stream.cache_invalidation')) {
            return;
        }

        try {
            Redis::connection('stream')->publish(self::CHANNEL, $message);
        } catch (Throwable $error) {
            // The gateway's cache TTL still ends stale entries; a Redis outage
            // must not block admin edits or the media worker's callbacks.
            report($error);
        }
    }
}
