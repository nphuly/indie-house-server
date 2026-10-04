<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\InviteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class InviteRedemptionController extends Controller
{
    public function show(string $code): Response
    {
        abort_unless(config('instance.registration_mode') === 'invite_only' && preg_match('/\A[a-f0-9]{64}\z/', $code), 404);

        return response()->view('auth.invite', compact('code'))
            ->header('Referrer-Policy', 'no-referrer')
            ->header('Cache-Control', 'no-store');
    }

    public function web(Request $request, InviteService $invites): RedirectResponse
    {
        abort_unless(config('instance.registration_mode') === 'invite_only', 404);
        $attributes = $this->validatePayload($request);
        $user = $invites->redeem($attributes['invite_code'], $attributes, $request->ip());
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('library.index');
    }

    public function mobile(Request $request, InviteService $invites): JsonResponse
    {
        abort_unless(config('instance.registration_mode') === 'invite_only', 404);
        $attributes = $this->validatePayload($request);
        $invites->redeem($attributes['invite_code'], $attributes, $request->ip());

        return response()->json(['message' => __('invite.created')], 201, ['Cache-Control' => 'no-store']);
    }

    /** @return array{invite_code: string, name: string, email: string, password: string} */
    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'invite_code' => ['required', 'string', 'size:64'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);
    }
}
