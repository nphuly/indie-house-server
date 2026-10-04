<?php

namespace App\Models\Media;

use App\Concerns\CleansS3Media;
use App\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Storage;

#[Fillable(['artist_id', 'title', 'slug', 'description', 'cover_image'])]
class Album extends Model
{
    use CleansS3Media, HasPublicId;

    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class, 'artist_id');
    }

    public function tracks(): BelongsToMany
    {
        return $this->belongsToMany(Track::class, 'album_tracks', 'album_id', 'track_id')
            ->withPivot('sort_order')
            ->orderByPivot('sort_order');
    }

    public function mediaPathsForDeletion(): array
    {
        return array_filter([$this->cover_image]);
    }

    public function mediaDirectoriesForDeletion(): array
    {
        return ["albums/{$this->id}"];
    }

    protected function coverUrl(): Attribute
    {
        return Attribute::get(
            fn () => $this->cover_image ? Storage::disk('s3')->temporaryUrl($this->cover_image, now()->addMinutes(15)) : null,
        );
    }

}
