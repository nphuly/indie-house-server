<?php

namespace App\Models\Auth;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'high_quality_enabled', 'lossless_enabled', 'streaming_blocked_until', 'streaming_block_reason'])]
class UserStreamAccess extends Model
{
    use HasUuids;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'high_quality_enabled' => 'boolean',
            'lossless_enabled' => 'boolean',
            'streaming_blocked_until' => 'datetime',
        ];
    }

    public function isStreamingBlocked(): bool
    {
        return $this->streaming_blocked_until?->isFuture() ?? false;
    }
}
