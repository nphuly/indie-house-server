<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InternalApiAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('X-Internal-Key');
        $expected = config('app.internal_api_key');

        // An unset INTERNAL_API_KEY refuses every call instead of failing
        // with a TypeError or matching an empty header.
        if (! is_string($expected) || $expected === '' || ! is_string($key) || $key === '' || ! hash_equals($expected, $key)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
