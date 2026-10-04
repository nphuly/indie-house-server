<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Admin uploads wait in a `_pending` folder until their record has an ID.
 */
class PendingUpload
{
    /**
     * Copies the upload to its final key and deletes the pending copy only once
     * the record is committed, so a failed save leaves the file where it was.
     *
     * @throws RuntimeException when object storage refuses the copy
     */
    public static function promote(string $from, string $to): void
    {
        $disk = Storage::disk('s3');

        if (! $disk->copy($from, $to)) {
            throw new RuntimeException("Could not copy the upload {$from} to {$to}.");
        }

        DB::afterCommit(fn () => $disk->delete($from));
    }
}
