<?php

use App\Http\Middleware\InternalApiAuth;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The public HTTPS proxy terminates TLS before Caddy. The edge sets
        // both headers itself: the public scheme, and X-Forwarded-For to the
        // one visitor address Cloudflare reported, so rate limits count per
        // visitor. Never trust forwarded host.
        $middleware->trustProxies(
            at: env('TRUSTED_PROXIES'),
            headers: Request::HEADER_X_FORWARDED_PROTO | Request::HEADER_X_FORWARDED_FOR,
        );
        $middleware->alias([
            'internal.api' => InternalApiAuth::class,
        ]);
        // A password change signs out every other browser session and
        // remember-me cookie; the session that changed it stays signed in.
        $middleware->web(append: [AuthenticateSession::class]);
        // Global, so 404 pages for unknown URLs are translated too. The
        // cookie only holds a language code, so it is not encrypted.
        $middleware->append(SetLocale::class);
        $middleware->append(SecurityHeaders::class);
        $middleware->encryptCookies(except: [SetLocale::COOKIE]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
