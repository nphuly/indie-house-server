<?php

namespace App\Providers;

use GuzzleHttp\Psr7\Uri;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use Psr\Http\Message\RequestInterface;

use function Aws\default_http_handler;

class StorageServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Laravel binds this closure to the filesystem manager, so name the class.
        Storage::extend('s3', function (Application $app, array $config): FilesystemAdapter {
            if (filled($config['internal_endpoint'] ?? null)) {
                $config['http_handler'] = StorageServiceProvider::sendThrough(
                    (string) $config['internal_endpoint'],
                    $config['http_handler'] ?? null,
                );
            }

            return $app['filesystem']->createS3Driver($config);
        });
    }

    /**
     * An HTTP handler that sends each signed API call to the internal address
     * and keeps the signed Host header, so MinIO accepts it. Presigned URLs
     * never reach the HTTP handler, so they stay on the public host.
     *
     * Without this, the control plane reaches MinIO through Cloudflare: each
     * call takes hundreds of milliseconds, and Cloudflare turns the first HEAD
     * on a new .flac/.jpg key into a GET, which fails the signature with 403.
     */
    public static function sendThrough(string $endpoint, ?callable $send = null): callable
    {
        $target = new Uri($endpoint);
        $send ??= default_http_handler();

        return static fn (RequestInterface $request, array $options) => $send(
            $request->withUri(
                $request->getUri()
                    ->withScheme($target->getScheme())
                    ->withHost($target->getHost())
                    ->withPort($target->getPort()),
                preserveHost: true,
            ),
            $options,
        );
    }
}
