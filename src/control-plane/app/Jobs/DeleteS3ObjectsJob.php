<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class DeleteS3ObjectsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300];

    /**
     * @param  array<int, string>  $paths
     * @param  array<int, string>  $directories
     */
    public function __construct(
        public array $paths = [],
        public array $directories = [],
    ) {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $storage = Storage::disk('s3');

        $paths = $this->normalize($this->paths);

        if ($paths !== []) {
            $storage->delete($paths);
        }

        foreach ($this->normalize($this->directories) as $directory) {
            $storage->deleteDirectory($directory);
        }
    }

    /**
     * @param  array<int, string>  $keys
     * @return array<int, string>
     */
    private function normalize(array $keys): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn (string $key): string => ltrim($key, '/'),
            $keys,
        ))));
    }
}
