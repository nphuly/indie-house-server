<?php

namespace App\Concerns;

use App\Jobs\DeleteS3ObjectsJob;

trait CleansS3Media
{
    /** @var array<int, string> */
    protected array $s3PathsToDelete = [];

    /** @var array<int, string> */
    protected array $s3DirectoriesToDelete = [];

    public static function bootCleansS3Media(): void
    {
        static::deleting(function (self $model): void {
            $model->s3PathsToDelete = $model->mediaPathsForDeletion();
            $model->s3DirectoriesToDelete = $model->mediaDirectoriesForDeletion();
        });

        static::deleted(function (self $model): void {
            DeleteS3ObjectsJob::dispatch(
                $model->s3PathsToDelete,
                $model->s3DirectoriesToDelete,
            )->afterCommit();
        });
    }

    /** @return array<int, string> */
    abstract public function mediaPathsForDeletion(): array;

    /** @return array<int, string> */
    abstract public function mediaDirectoriesForDeletion(): array;
}
