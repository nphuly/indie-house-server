<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Picks the language for every request, including error pages that render
 * before a session exists.
 *
 * Browsers carry the choice in a plain cookie shared with the admin panel's
 * language switcher. Mobile clients send `Accept-Language`.
 */
class SetLocale
{
    public const COOKIE = 'filament_language_switcher_locale';

    public function handle(Request $request, Closure $next): Response
    {
        App::setLocale(self::resolve($request));

        return $next($request);
    }

    public static function resolve(Request $request): string
    {
        $default = config('app.locale');
        $supported = self::supported();

        if ($request->is('api/*')) {
            if (! $request->headers->has('Accept-Language')) {
                return $default;
            }

            // The default comes first so an unsupported language falls back to it.
            return $request->getPreferredLanguage(array_values(array_unique([$default, ...$supported])));
        }

        $chosen = $request->cookies->get(self::COOKIE);

        if (in_array($chosen, $supported, true)) {
            return $chosen;
        }

        // Drop an unusable value (for example one written while the cookie
        // was still encrypted) so the admin panel does not apply it either.
        $request->cookies->remove(self::COOKIE);

        return $default;
    }

    /** @return list<string> */
    public static function supported(): array
    {
        return config('app.supported_locales', [config('app.locale')]);
    }
}
