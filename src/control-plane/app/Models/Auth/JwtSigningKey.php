<?php

namespace App\Models\Auth;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['kid', 'public_key_fingerprint', 'status', 'activated_at', 'retired_at', 'created_by_user_id'])]
class JwtSigningKey extends Model
{
    use HasUuids;

    public const string STAGED = 'staged';

    public const string ACTIVE = 'active';

    public const string RETIRED = 'retired';

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('status', self::ACTIVE);
    }

    protected function casts(): array
    {
        return [
            'activated_at' => 'datetime',
            'retired_at' => 'datetime',
        ];
    }
}
