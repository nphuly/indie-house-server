<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Auth\User;
use App\Services\Auth\MobileSessionLimitException;
use App\Services\Auth\MobileTokenService;
use App\Services\Auth\RefreshTokenReplayException;
use App\Services\Auth\StreamingBlockedException;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Throwable;

class MobileTokenController extends Controller
{
    public function __construct(private readonly MobileTokenService $tokens) {}

    public function store(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:120'],
            'platform' => ['nullable', 'string', 'max:32'],
        ]);
        $throttle = $this->throttle();

        // Five failures per account and address, and a cap per address so one
        // client cannot try many accounts.
        if ((int) $throttle->get($this->loginThrottleKey($request, (string) $credentials['email']), 0) >= 5
            || ! $this->allowRequest($throttle, 'login', $request, 30)) {
            return $this->tooManyRequests();
        }

        $user = User::query()->where('email', $credentials['email'])->first();

        if (! $user instanceof User || ! Hash::check($credentials['password'], $user->password)) {
            $this->recordFailedLogin($throttle, $request, (string) $credentials['email']);

            return response()->json(['message' => __('mobile_api.invalid_credentials')], 401);
        }

        $throttle->forget($this->loginThrottleKey($request, (string) $credentials['email']));

        return $this->tokenResponse(fn (): array => $this->tokens->issue($user, $this->metadata($request, $credentials)));
    }

    public function refresh(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'refresh_token' => ['required', 'string', 'max:256'],
            'device_name' => ['nullable', 'string', 'max:120'],
            'platform' => ['nullable', 'string', 'max:32'],
        ]);
        $throttle = $this->throttle();

        if (! $this->allowRequest($throttle, 'refresh', $request, 30)) {
            return $this->tooManyRequests();
        }

        return $this->tokenResponse(function () use ($payload, $request): ?array {
            return $this->tokens->refresh($payload['refresh_token'], $this->metadata($request, $payload));
        });
    }

    public function destroy(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'refresh_token' => ['required', 'string', 'max:256'],
        ]);
        $throttle = $this->throttle();

        if (! $this->allowRequest($throttle, 'logout', $request, 30)) {
            return $this->tooManyRequests();
        }

        try {
            $this->tokens->revoke($payload['refresh_token']);
        } catch (Throwable) {
            // Logout is intentionally idempotent. Do not reveal whether a
            // token existed, and do not turn a best-effort revoke into 500.
        }

        return response()->json(status: 204);
    }

    /** @param callable(): (array{access_token: string, refresh_token: string, token_type: string, expires_in: int}|null) $issue */
    private function tokenResponse(callable $issue): JsonResponse
    {
        try {
            $tokens = $issue();
        } catch (StreamingBlockedException) {
            return response()->json(['message' => __('mobile_api.streaming_blocked')], 403);
        } catch (MobileSessionLimitException) {
            return response()->json(['message' => __('settings.session_limit')], 429);
        } catch (RefreshTokenReplayException) {
            return response()->json(['message' => __('mobile_api.invalid_refresh')], 401);
        } catch (RuntimeException) {
            return response()->json(['message' => __('mobile_api.not_configured')], 503);
        }

        if ($tokens === null) {
            return response()->json(['message' => __('mobile_api.invalid_refresh')], 401);
        }

        return response()->json($tokens, 200, ['Cache-Control' => 'no-store']);
    }

    private function throttle(): Repository
    {
        try {
            return Cache::store((string) config('stream.auth_rate_limit.store'));
        } catch (Throwable) {
            abort(503, 'Mobile authentication rate limiter is unavailable.');
        }
    }

    private function recordFailedLogin(Repository $throttle, Request $request, string $email): void
    {
        $key = $this->loginThrottleKey($request, $email);

        if (! $throttle->add($key, 1, now()->addMinute())) {
            $throttle->increment($key);
        }
    }

    private function loginThrottleKey(Request $request, string $email): string
    {
        return 'stream:auth:login:'.hash('sha256', strtolower($email).'|'.$request->ip());
    }

    private function allowRequest(Repository $throttle, string $action, Request $request, int $limit): bool
    {
        $window = (int) floor(now()->timestamp / 60);
        $key = "stream:auth:rate:{$action}:{$window}:".hash('sha256', (string) $request->ip());
        $attempts = (int) $throttle->get($key, 0);

        if ($attempts >= $limit) {
            return false;
        }

        if (! $throttle->add($key, 1, now()->addMinute())) {
            $throttle->increment($key);
        }

        return true;
    }

    private function tooManyRequests(): JsonResponse
    {
        return response()->json(['message' => __('mobile_api.too_many_requests')], 429, ['Retry-After' => '60']);
    }

    /** @param array<string, mixed> $payload */
    private function metadata(Request $request, array $payload): array
    {
        return [
            'device_name' => $payload['device_name'] ?? null,
            'platform' => $payload['platform'] ?? null,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 512),
        ];
    }
}
