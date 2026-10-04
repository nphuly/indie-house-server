<?php

namespace App\Models\Media;

use App\Concerns\CleansS3Media;
use App\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Auth\User;

#[Fillable(['user_id', 'title', 'description', 'cover_image', 'is_public'])]
class Playlist extends Model
{
    use CleansS3Media, HasPublicId, HasUuids;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tracks(): BelongsToMany
    {
        return $this->belongsToMany(Track::class, 'playlist_tracks', 'playlist_id', 'track_id')
            ->withPivot(['sort_order', 'added_at']);
    }

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
        ];
    }

    public function mediaPathsForDeletion(): array
    {
        return array_filter([$this->cover_image]);
    }

    public function mediaDirectoriesForDeletion(): array
    {
        return ["playlists/{$this->id}"];
    }
}
