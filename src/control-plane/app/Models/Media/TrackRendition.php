<?php

namespace App\Models\Media;

use App\Concerns\CleansS3Media;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['track_id', 'quality_label', 'format', 'bitrate_kbps', 'file_key', 'file_size_bytes'])]
class TrackRendition extends Model
{
    use CleansS3Media;

    public $timestamps = false;

    public function track(): BelongsTo
    {
        return $this->belongsTo(Track::class, 'track_id');
    }

    public function mediaPathsForDeletion(): array
    {
        return array_filter([$this->file_key]);
    }

    public function mediaDirectoriesForDeletion(): array
    {
        return $this->format === 'hls' ? [dirname($this->file_key)] : [];
    }
}
