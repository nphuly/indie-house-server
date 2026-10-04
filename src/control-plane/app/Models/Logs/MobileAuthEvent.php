<?php

namespace App\Models\Logs;

use App\Models\Auth\MobileSession;
use App\Models\Auth\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MobileAuthEvent extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $guarded = [];

    public function session(): BelongsTo
    {
        return $this->belongsTo(MobileSession::class, 'mobile_session_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }
}
