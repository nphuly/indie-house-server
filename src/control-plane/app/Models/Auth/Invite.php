<?php

namespace App\Models\Auth;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invite extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(InviteRedemption::class);
    }

    public function isUsableFor(string $email): bool
    {
        return $this->revoked_at === null
            && $this->expires_at->isFuture()
            && $this->uses_count < $this->max_uses
            && ($this->email === null || strcasecmp($this->email, $email) === 0);
    }
}
