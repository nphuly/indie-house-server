<?php

namespace App\Providers;

use App\Models\Media\Track;
use App\Models\Media\TrackRendition;
use App\Observers\StreamCacheObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Track::observe(StreamCacheObserver::class);
        TrackRendition::observe(StreamCacheObserver::class);

        // Limit login attempts per account, and per visitor IP (which the
        // edge forwards) so one address cannot try many accounts. Several
        // listeners can share an address behind NAT, so that quota is larger.
        RateLimiter::for('web-login', static function (Request $request): array {
            $email = Str::lower(trim((string) $request->input('email', '')));

            return [
                Limit::perMinute(5)->by('web-login-email:'.hash('sha256', $email)),
                Limit::perMinute(30)->by('web-login-ip:'.($request->ip() ?? 'unknown')),
            ];
        });

        View::composer('listen.*', function ($view): void {
            $request = request();
            $cacheKey = 'listen.user-playlists';

            if (! $request->attributes->has($cacheKey)) {
                $request->attributes->set($cacheKey, Auth::check()
                    ? Auth::user()->playlists()
                        ->withCount(['tracks' => fn ($query) => $query->publicCatalogue()])
                        ->orderBy('title')
                        ->get(['id', 'public_id', 'title', 'is_public'])
                    : collect());
            }

            $view->with('userPlaylists', $request->attributes->get($cacheKey));
        });
    }
}
