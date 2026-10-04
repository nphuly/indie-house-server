<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Roles;
use App\Http\Controllers\Controller;
use App\Models\Auth\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(Request $request): View
    {
        $this->rememberIntendedUrl($request);

        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('library.index'));
    }

    public function createRegistration(Request $request): View
    {
        $this->ensurePublicRegistrationIsEnabled();
        $this->rememberIntendedUrl($request);

        return view('auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $this->ensurePublicRegistrationIsEnabled();

        $attributes = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = User::create([
            'name' => $attributes['name'],
            'email' => $attributes['email'],
            'password' => Hash::make($attributes['password']),
        ]);
        $user->assignRole(Roles::USER->value);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('library.index'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('listen.home');
    }

    private function rememberIntendedUrl(Request $request): void
    {
        $intended = (string) $request->query('intended', '');

        $parts = parse_url($intended);

        if (
            $intended !== ''
            && is_array($parts)
            && ($parts['host'] ?? null) === $request->getHost()
            && ($parts['scheme'] ?? null) === $request->getScheme()
        ) {
            $request->session()->put('url.intended', $intended);
        }
    }

    private function ensurePublicRegistrationIsEnabled(): void
    {
        abort_unless(config('instance.registration_mode') === 'open', 404);
    }
}
