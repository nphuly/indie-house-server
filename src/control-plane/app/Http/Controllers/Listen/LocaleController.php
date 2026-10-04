<?php

namespace App\Http\Controllers\Listen;

use App\Http\Controllers\Controller;
use App\Http\Middleware\SetLocale;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class LocaleController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $locale = $request->validate([
            'locale' => ['required', 'string', Rule::in(SetLocale::supported())],
        ])['locale'];

        // The admin panel's switcher reads the session first, then the cookie.
        $request->session()->put('locale', $locale);
        $cookie = cookie()->forever(SetLocale::COOKIE, $locale);

        if ($request->expectsJson()) {
            return response()->json(['locale' => $locale])->withCookie($cookie);
        }

        return back()->withCookie($cookie);
    }
}
