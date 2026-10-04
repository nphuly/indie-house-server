<?php

namespace App\Console\Commands;

use App\Models\Auth\User;
use App\Models\Media\Album;
use App\Models\Media\Artist;
use App\Models\Media\Track;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Older releases ignored a failed storage move: records were saved with their
 * final key while the file stayed in a `_pending` folder. Artist covers went to
 * the app's own disk, and profile pictures to the `public` disk, both inside
 * the container. This puts those files where the records point.
 */
class RepairPendingUploads extends Command
{
    protected $signature = 'media:repair-uploads
        {--dry-run : List what would change without touching storage or the database}';

    protected $description = 'Move uploads stranded in _pending folders to the keys their records point at';

    private Filesystem $s3;

    /** @var array<int, array{string, string}> */
    private array $unrepaired = [];

    private int $repaired = 0;

    public function handle(): int
    {
        $this->s3 = Storage::disk('s3');
        $dryRun = (bool) $this->option('dry-run');

        foreach (Track::query()->orderBy('created_at')->get() as $track) {
            if (blank($track->raw_file_key)) {
                $this->unrepaired[] = ["track {$track->id} ({$track->title})", 'no audio file saved; upload it again'];

                continue;
            }

            $candidates = $this->pendingCopies($track->raw_file_key, 'tracks/_pending');
            if ($this->repair($track, 'raw_file_key', $candidates, $dryRun) && ! $dryRun && $track->status !== 'completed') {
                $track->dispatchProcessing(deleteProcessedFiles: false);
                $this->line("  queued processing for track {$track->id}");
            }
        }

        foreach (Artist::query()->orderBy('id')->get() as $artist) {
            foreach (['avatar', 'cover_image'] as $field) {
                $this->repair($artist, $field, $this->pendingCopies($artist->{$field}, 'artists/_pending'), $dryRun);
            }
        }

        foreach (Album::query()->orderBy('id')->get() as $album) {
            $this->repair($album, 'cover_image', $this->pendingCopies($album->cover_image, 'albums/_pending'), $dryRun);
        }

        foreach (User::query()->whereNotNull('avatar_path')->orderBy('id')->get() as $user) {
            $this->repair($user, 'avatar_path', [[Storage::disk('public'), $user->avatar_path]], $dryRun);
        }

        if ($this->unrepaired !== []) {
            $this->newLine();
            $this->warn('Not repaired:');
            $this->table(['Record', 'Problem'], $this->unrepaired);
        }

        $this->listLeftovers();

        $this->newLine();
        $this->info($dryRun
            ? "Dry run: {$this->repaired} file(s) would be moved."
            : "Moved {$this->repaired} file(s).");

        return self::SUCCESS;
    }

    /**
     * Returns true when the record's file was (or would be) put in place.
     *
     * @param  array<int, array{Filesystem, string}>  $candidates  places the file may be instead, in order
     */
    private function repair(Model $record, string $field, array $candidates, bool $dryRun): bool
    {
        $key = $record->{$field};
        $label = class_basename($record).' '.$record->getKey()." {$field}";

        if (blank($key) || $this->s3->exists($key)) {
            return false;
        }

        foreach ($candidates as [$disk, $path]) {
            if (! $disk->exists($path)) {
                continue;
            }

            $this->line(($dryRun ? 'would move ' : 'moving ').$this->describe($disk, $path)." -> {$key} ({$label})");

            if (! $dryRun && ! $this->moveToS3($disk, $path, $key)) {
                $this->unrepaired[] = [$label, "could not move {$path} to {$key}"];

                return false;
            }

            $this->repaired++;

            return true;
        }

        $this->unrepaired[] = [$label, "{$key} is missing and no other copy was found; upload it again"];

        return false;
    }

    /**
     * @return array<int, array{Filesystem, string}>
     */
    private function pendingCopies(?string $key, string $pendingDirectory): array
    {
        if (blank($key)) {
            return [];
        }

        $pending = $pendingDirectory.'/'.basename($key);
        $candidates = [[$this->s3, $pending]];

        // Older releases saved artist covers to Filament's default disk.
        $defaultDisk = (string) config('filament.default_filesystem_disk');

        if ($defaultDisk !== '' && $defaultDisk !== 's3' && config("filesystems.disks.{$defaultDisk}")) {
            $local = Storage::disk($defaultDisk);
            $candidates[] = [$local, $key];
            $candidates[] = [$local, $pending];
        }

        return $candidates;
    }

    private function moveToS3(Filesystem $disk, string $from, string $to): bool
    {
        try {
            if ($disk === $this->s3) {
                return $this->s3->copy($from, $to) && $this->s3->delete($from);
            }

            $stream = $disk->readStream($from);

            return is_resource($stream) && $this->s3->writeStream($to, $stream) && $disk->delete($from);
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }

    private function describe(Filesystem $disk, string $path): string
    {
        return $disk === $this->s3 ? $path : "{$path} (app disk)";
    }

    /**
     * Uploads that no record points at, so an admin can see what to upload again
     * before media:purge-pending-uploads deletes them.
     */
    private function listLeftovers(): void
    {
        $rows = [];

        foreach (['tracks/_pending', 'artists/_pending', 'albums/_pending', 'livewire-tmp'] as $directory) {
            foreach ($this->s3->files($directory) as $path) {
                $rows[] = [
                    $path,
                    $this->originalName($path) ?? '',
                    number_format($this->s3->size($path) / 1048576, 1).' MB',
                    date('Y-m-d H:i', $this->s3->lastModified($path)).' UTC',
                ];
            }
        }

        if ($rows === []) {
            return;
        }

        $this->newLine();
        $this->warn('Uploads no record points at (media:purge-pending-uploads deletes them after 24 hours):');
        $this->table(['Path', 'Original name', 'Size', 'Uploaded'], $rows);
    }

    /**
     * Livewire embeds the browser's file name in its temporary file name.
     */
    private function originalName(string $path): ?string
    {
        if (! str_contains($path, '-meta')) {
            return null;
        }

        $encoded = strtok(last(explode('-meta', str_replace('_', '/', basename($path)))), '-');
        $name = base64_decode((string) $encoded, true);

        return $name === false ? null : $name;
    }
}
