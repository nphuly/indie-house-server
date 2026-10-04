<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline headers for every response. A response that already sets one of
 * them keeps its own value: the invite page, for example, sends no referrer.
 *
 * The Content-Security-Policy has two levels. Listener, auth and legacy pages
 * run only same-origin scripts that carry this request's nonce. The admin
 * panel cannot: Filament prints inline scripts without a nonce and Alpine
 * evaluates expressions, so it allows inline code but still only loads
 * scripts from this origin.
 */
class SecurityHeaders
{
    private const HEADERS = [
        // No page is meant to be framed. This stops clickjacking of the admin
        // panel and of forms such as settings and playlist deletion.
        'X-Frame-Options' => 'SAMEORIGIN',
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $admin = $request->is('admin', 'admin/*');

        // Before the views render, so @vite tags carry the nonce.
        if (! $admin) {
            Vite::useCspNonce();
        }

        $response = $next($request);

        foreach (self::HEADERS as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        if (! $response->headers->has('Content-Security-Policy')) {
            $response->headers->set('Content-Security-Policy', $this->policy($admin));
        }

        // Only over HTTPS, which the edge reports. Subdomains are left out so
        // the policy never reaches another service on the same domain.
        if ($request->isSecure() && ! $response->headers->has('Strict-Transport-Security')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        return $response;
    }

    private function policy(bool $admin): string
    {
        // Artwork and admin previews are presigned object storage URLs; public
        // disk URLs (avatars) are built from APP_URL.
        $storage = $this->origins([
            config('filesystems.disks.s3.url'),
            config('filesystems.disks.s3.endpoint'),
            config('filesystems.disks.s3.temporary_url'),
        ]) ?: ['https://*.amazonaws.com'];
        $app = $this->origins([config('app.url')]);
        [$dev, $devSocket] = $this->viteDevServer();

        $policy = [
            'default-src' => ["'self'"],
            'base-uri' => ["'self'"],
            'object-src' => ["'none'"],
            'frame-ancestors' => ["'self'"],
            'form-action' => ["'self'"],
            'script-src' => ["'self'", "'nonce-".Vite::cspNonce()."'", ...$dev],
            // Style attributes and the @fonts block are inline; styles cannot run code.
            'style-src' => ["'self'", "'unsafe-inline'", ...$dev],
            'font-src' => ["'self'", 'data:', ...$dev],
            'img-src' => ["'self'", 'data:', ...$app, ...$storage, ...$dev],
            'media-src' => ["'self'"],
            'connect-src' => ["'self'", ...$dev, ...$devSocket],
        ];

        if ($admin) {
            $policy['script-src'] = ["'self'", "'unsafe-inline'", "'unsafe-eval'", ...$dev];
            // Filament's font comes from Bunny Fonts.
            $policy['style-src'][] = 'https://fonts.bunny.net';
            $policy['font-src'][] = 'https://fonts.bunny.net';
            // FilePond previews blobs in a worker; uploads go straight to
            // object storage, and track previews play from it.
            $policy['img-src'][] = 'blob:';
            $policy['worker-src'] = ["'self'", 'blob:'];
            $policy['connect-src'] = [...$policy['connect-src'], ...$storage];
            $policy['media-src'] = ["'self'", ...$storage];
        }

        return collect($policy)
            ->map(fn (array $sources, string $directive): string => $directive.' '.implode(' ', array_unique($sources)))
            ->implode('; ');
    }

    /**
     * @param  array<int, mixed>  $urls
     * @return list<string>
     */
    private function origins(array $urls): array
    {
        $origins = [];

        foreach ($urls as $url) {
            $parts = is_string($url) ? parse_url($url) : false;

            if (is_array($parts) && isset($parts['scheme'], $parts['host'])) {
                $origins[] = $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
            }
        }

        return array_values(array_unique($origins));
    }

    /**
     * `npm run dev` serves scripts, styles and fonts from its own origin and
     * pushes updates over a websocket.
     *
     * @return array{0: list<string>, 1: list<string>}
     */
    private function viteDevServer(): array
    {
        if (! Vite::isRunningHot()) {
            return [[], []];
        }

        $origins = $this->origins([trim((string) @file_get_contents(Vite::hotFile()))]);

        return [$origins, array_map(fn (string $origin): string => preg_replace('~^http~', 'ws', $origin), $origins)];
    }
}
