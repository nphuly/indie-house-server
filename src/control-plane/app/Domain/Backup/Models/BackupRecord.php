<?php

namespace App\Domain\Backup\Models;

use App\Domain\Backup\Enums\BackupStatus;
use App\Domain\Backup\Enums\BackupType;
use App\Models\Auth\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackupRecord extends Model
{
    use HasUuids;

    protected $fillable = [
        'filename',
        'path',
        'size_bytes',
        'status',
        'type',
        'error_message',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => BackupStatus::class,
            'type' => BackupType::class,
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
