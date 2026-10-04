<?php

namespace App\Models\Auth;

use App\Models\Logs\MobileAuthEvent;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MobileSession extends Model
{
    use HasUuids;

    protected $guarded = [];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(MobileAuthEvent::class);
    }

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'replay_detected_at' => 'datetime',
        ];
    }

    public function isUsable(): bool
    {
        return $this->revoked_at === null && $this->expires_at->isFuture();
    }

    public function revoke(string $reason): void
    {
        if ($this->revoked_at !== null) {
            return;
        }

        $this->forceFill([
            'revoked_at' => now(),
            'revoked_reason' => $reason,
        ])->save();
    }
}
