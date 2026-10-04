<?php

namespace App\Models\Media;

use App\Concerns\CleansS3Media;
use App\Concerns\HasPublicId;
use App\Enums\TrackFormats;
use App\Enums\TrackRenditions;
use App\Jobs\ProcessTrackJob;
use App\Models\Logs\UserPlayLog;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'artist_id', 'album_id', 'title', 'slug', 'duration_seconds', 'genre', 'lyrics', 'raw_file_key',
    'status', 'last_error_message', 'is_published', 'play_count',
])]
class Track extends Model
{
    use CleansS3Media, HasPublicId, HasUuids;

    const string MASTER_M_3_U_8 = 'master.m3u8';

    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class, 'artist_id');
    }

    public function albums(): BelongsToMany
    {
        return $this->belongsToMany(Album::class, 'album_tracks', 'track_id', 'album_id')
            ->withPivot('sort_order');
    }

    public function renditions(): HasMany
    {
        return $this->hasMany(TrackRendition::class);
    }

    public function playLogs(): HasMany
    {
        return $this->hasMany(UserPlayLog::class);
    }

    public function hasPreviewRendition(): bool
    {
        return $this->renditions()
            ->where('quality_label', TrackRenditions::MP3_128->value)
            ->exists();
    }

    public function previewRendition(): ?TrackRendition
    {
        return $this->renditions()
            ->where('format', TrackFormats::MP3->value)
            ->orderByRaw("case quality_label when 'mp3_128' then 0 when 'mp3_320' then 1 else 2 end")
            ->first();
    }

    public function getPreviewFileKeyAttribute(): ?string
    {
        return $this->previewRendition()?->file_key ?? $this->raw_file_key;
    }

    public function getPreviewUrlAttribute(): ?string
    {
        if (! $this->preview_file_key) {
            return null;
        }

        return Storage::disk('s3')->temporaryUrl($this->preview_file_key, now()->addMinutes(30));
    }

    public function getHlsMasterUrlAttribute(): ?string
    {
        $rendition = $this->renditions()
            ->where('format', TrackFormats::HLS->value)
            ->where('quality_label', TrackRenditions::HLS_128->value)
            ->first();

        if (! $rendition) {
            return null;
        }

        return Storage::disk('s3')->temporaryUrl(
            dirname($rendition->file_key).'/'.self::MASTER_M_3_U_8,
            now()->addMinutes(30),
        );
    }

    public function getFormattedDurationAttribute(): string
    {
        $seconds = max(0, (int) $this->duration_seconds);

        return sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
    }

    public function resetProcessingState(bool $deleteProcessedFiles = true): void
    {
        if ($deleteProcessedFiles) {
            $this->renditions()->get()->each(function (TrackRendition $rendition): void {
                Storage::disk('s3')->delete($rendition->file_key);

                if ($rendition->format === TrackFormats::HLS->value) {
                    Storage::disk('s3')->deleteDirectory(dirname($rendition->file_key));
                }
            });
        }

        $this->renditions()->delete();

        $this->forceFill([
            'status' => 'pending',
            'duration_seconds' => 0,
            'last_error_message' => null,
        ])->save();
    }

    public function dispatchProcessing(bool $deleteProcessedFiles = true): void
    {
        $this->resetProcessingState($deleteProcessedFiles);

        ProcessTrackJob::dispatch($this->id);
    }

    public static function statusLabel(?string $status): string
    {
        return match ($status) {
            'pending', 'processing', 'completed', 'failed' => __("track.statuses.{$status}"),
            default => __('track.statuses.unknown'),
        };
    }

    public static function statusColor(?string $status): string
    {
        return match ($status) {
            'completed' => 'success',
            'processing' => 'info',
            'failed' => 'danger',
            'pending' => 'warning',
            default => 'gray',
        };
    }

    public function mediaPathsForDeletion(): array
    {
        return [
            ...array_filter([$this->raw_file_key]),
            ...$this->renditions->pluck('file_key')->filter()->all(),
        ];
    }

    public function mediaDirectoriesForDeletion(): array
    {
        $directories = ["processed/{$this->id}"];
        $rawDirectory = dirname((string) $this->raw_file_key);

        if (str_starts_with((string) $this->raw_file_key, 'tracks/raw/') && str_ends_with($rawDirectory, "/{$this->id}")) {
            $directories[] = $rawDirectory;
        }

        return $directories;
    }

    /**
     * The only tracks that may be exposed in the listener catalogue.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePublicCatalogue(Builder $query): Builder
    {
        return $query
            ->where('is_published', true)
            ->where('status', 'completed');
    }

    public function getArtworkUrlAttribute(): ?string
    {
        $album = $this->relationLoaded('albums')
            ? $this->albums->first()
            : $this->albums()->first();

        $coverUrl = $album?->cover_url;

        if ($coverUrl) {
            return $coverUrl;
        }

        $artist = $this->relationLoaded('artist') ? $this->artist : $this->artist()->first();

        return $artist?->avatar_url;
    }
}
