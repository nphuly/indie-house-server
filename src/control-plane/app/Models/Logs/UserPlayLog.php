<?php

namespace App\Models\Logs;

use App\Models\Auth\User;
use App\Models\Media\Track;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'track_id', 'played_duration_seconds', 'completed', 'ip_address', 'user_agent'])]
class UserPlayLog extends Model
{
    public const UPDATED_AT = null;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function track(): BelongsTo
    {
        return $this->belongsTo(Track::class);
    }

    protected $casts = [
        'completed' => 'boolean'
    ];
}
