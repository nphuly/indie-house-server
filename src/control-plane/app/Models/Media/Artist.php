<?php

namespace App\Models\Media;

use App\Concerns\CleansS3Media;
use App\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

#[Fillable(['name', 'slug', 'bio', 'avatar', 'cover_image'])]
class Artist extends Model
{
    use CleansS3Media, HasPublicId;

    public function albums(): HasMany
    {
        return $this->hasMany(Album::class);
    }

    public function tracks(): HasMany
    {
        return $this->hasMany(Track::class)->latest();
    }

    protected function avatarUrl(): Attribute
    {
        return Attribute::get(
            fn () => $this->avatar ? Storage::disk('s3')->temporaryUrl($this->avatar, now()->addMinutes(15)) : null
        );
    }

    protected function coverUrl(): Attribute
    {
        return Attribute::get(
            fn () => $this->cover_image ? Storage::disk('s3')->temporaryUrl($this->cover_image, now()->addMinutes(15)) : null
        );
    }

    public function mediaPathsForDeletion(): array
    {
        $albumCovers = $this->albums()->pluck('cover_image')->all();
        $trackMedia = $this->tracks()->with('renditions')->get()->flatMap(
            fn (Track $track): array => $track->mediaPathsForDeletion(),
        )->all();

        return [...array_filter([$this->avatar, $this->cover_image]), ...$albumCovers, ...$trackMedia];
    }

    public function mediaDirectoriesForDeletion(): array
    {
        $albumDirectories = $this->albums()->pluck('id')->map(
            fn (int $id): string => "albums/{$id}",
        )->all();
        $trackDirectories = $this->tracks()->get()->flatMap(
            fn (Track $track): array => $track->mediaDirectoriesForDeletion(),
        )->all();

        return ["artists/{$this->id}", ...$albumDirectories, ...$trackDirectories];
    }
}
