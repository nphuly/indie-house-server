<?php

namespace App\Services\Auth;

use App\Models\Auth\User;
use App\Models\Auth\UserStreamAccess;

class StreamingAccessService
{
    /** @return array{high_quality_enabled: bool, lossless_enabled: bool, blocked: bool, block_reason: ?string} */
    public function forUser(User $user): array
    {
        $access = $user->streamAccess()->first();

        if (! $access instanceof UserStreamAccess) {
            return [
                // Preserve the existing mobile-token behaviour until an admin
                // creates an explicit per-user policy.
                'high_quality_enabled' => (bool) config('stream.access.default_high_quality_enabled', true),
                // Lossless is granted one listener at a time.
                'lossless_enabled' => false,
                'blocked' => false,
                'block_reason' => null,
            ];
        }

        return [
            'high_quality_enabled' => $access->high_quality_enabled,
            'lossless_enabled' => $access->lossless_enabled,
            'blocked' => $access->isStreamingBlocked(),
            'block_reason' => $access->streaming_block_reason,
        ];
    }
}
