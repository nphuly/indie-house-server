<?php

namespace App\Services\Auth;

use App\Models\Auth\MobileSession;
use App\Models\Auth\User;
use App\Models\Logs\MobileAuthEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class MobileTokenService
{
    public function __construct(
        private readonly StreamingAccessService $access,
        private readonly StreamJwtSigner $signer,
    ) {}

    /**
     * @param  array{device_name?: ?string, platform?: ?string, ip_address?: ?string, user_agent?: ?string}  $metadata
     * @return array{access_token: string, refresh_token: string, token_type: string, expires_in: int}
     */
    public function issue(User $user, array $metadata = []): array
    {
        $access = $this->streamAccessFor($user, $metadata, 'login_blocked');

        return DB::transaction(function () use ($user, $metadata, $access): array {
            User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
            $activeSessions = $user->mobileSessions()->whereNull('revoked_at')->where('expires_at', '>', now())->count();
            if ($activeSessions >= max(1, (int) config('stream.refresh.max_sessions_per_user', 10))) {
                throw new MobileSessionLimitException('Too many active mobile sessions.');
            }

            $now = now();
            $refreshToken = $this->newRefreshToken();
            $session = MobileSession::query()->create([
                'user_id' => $user->getKey(),
                'token_hash' => $this->tokenHash($refreshToken),
                'password_fingerprint' => $this->passwordFingerprint($user),
                'device_name' => $metadata['device_name'] ?? null,
                'platform' => $metadata['platform'] ?? null,
                'ip_address' => $metadata['ip_address'] ?? null,
                'user_agent' => $metadata['user_agent'] ?? null,
                'last_used_at' => $now,
                'expires_at' => $now->copy()->addDays($this->refreshTtlDays()),
            ]);
            $this->log($session, 'login', $metadata);

            return $this->tokenResponse($user, $refreshToken, $access);
        });
    }

    /**
     * @param  array{device_name?: ?string, platform?: ?string, ip_address?: ?string, user_agent?: ?string}  $metadata
     * @return array{access_token: string, refresh_token: string, token_type: string, expires_in: int}|null
     */
    public function refresh(string $refreshToken, array $metadata = []): ?array
    {
        if (! preg_match('/\A[a-f0-9]{96}\z/', $refreshToken)) {
            return null;
        }

        $result = DB::transaction(function () use ($refreshToken, $metadata): array|RefreshTokenReplayException|null {
            $hash = $this->tokenHash($refreshToken);
            $session = MobileSession::query()
                ->where(fn ($query) => $query->where('token_hash', $hash)->orWhere('previous_token_hash', $hash))
                ->lockForUpdate()
                ->first();

            if (! $session instanceof MobileSession) {
                return null;
            }

            if ($session->previous_token_hash !== null && hash_equals($session->previous_token_hash, $hash)) {
                $session->forceFill([
                    'replay_detected_at' => now(),
                    'revoked_at' => now(),
                    'revoked_reason' => 'refresh_replay_detected',
                ])->save();
                $this->log($session, 'refresh_replay_detected', $metadata);

                // Return the signal so the transaction commits the revoke and
                // audit entry before the caller receives an error.
                return new RefreshTokenReplayException('Refresh token replay detected.');
            }

            if (! $session->isUsable()) {
                return null;
            }

            $user = $session->user;

            if (! $user instanceof User || ! hash_equals($session->password_fingerprint, $this->passwordFingerprint($user))) {
                $session->revoke('password_changed_or_user_unavailable');

                return null;
            }

            $access = $this->streamAccessFor($user, $metadata, 'refresh_blocked', $session);
            $nextRefreshToken = $this->newRefreshToken();
            $now = now();
            $session->forceFill([
                'previous_token_hash' => $session->token_hash,
                'token_hash' => $this->tokenHash($nextRefreshToken),
                'last_used_at' => $now,
                'expires_at' => $now->copy()->addDays($this->refreshTtlDays()),
                'device_name' => $metadata['device_name'] ?? $session->device_name,
                'platform' => $metadata['platform'] ?? $session->platform,
                'ip_address' => $metadata['ip_address'] ?? $session->ip_address,
                'user_agent' => $metadata['user_agent'] ?? $session->user_agent,
            ])->save();
            $this->log($session, 'refresh', $metadata);

            return $this->tokenResponse($user, $nextRefreshToken, $access);
        });

        if ($result instanceof RefreshTokenReplayException) {
            throw $result;
        }

        return $result;
    }

    public function revoke(string $refreshToken, string $reason = 'user_logout', ?User $actor = null): void
    {
        if (! preg_match('/\A[a-f0-9]{96}\z/', $refreshToken)) {
            return;
        }

        $hash = $this->tokenHash($refreshToken);
        $session = MobileSession::query()
            ->where(fn ($query) => $query->where('token_hash', $hash)->orWhere('previous_token_hash', $hash))
            ->first();

        if (! $session instanceof MobileSession) {
            return;
        }

        $session->revoke($reason);
        $this->log($session, 'session_revoked', [], $actor, ['reason' => $reason]);
    }

    public function revokeAllForUser(User $user, string $reason, ?User $actor = null): int
    {
        $sessions = $user->mobileSessions()->whereNull('revoked_at')->get();

        foreach ($sessions as $session) {
            $session->revoke($reason);
            $this->log($session, 'session_revoked', [], $actor, ['reason' => $reason]);
        }

        return $sessions->count();
    }

    public function revokeSession(MobileSession $session, string $reason, ?User $actor = null): void
    {
        $session->revoke($reason);
        $this->log($session, 'session_revoked', [], $actor, ['reason' => $reason]);
    }

    /** @return array{high_quality_enabled: bool, lossless_enabled: bool, blocked: bool, block_reason: ?string} */
    private function streamAccessFor(User $user, array $metadata, string $event, ?MobileSession $session = null): array
    {
        $access = $this->access->forUser($user);

        if ($access['blocked']) {
            if ($session instanceof MobileSession) {
                $this->log($session, $event, $metadata, null, ['reason' => $access['block_reason']]);
            } else {
                MobileAuthEvent::query()->create([
                    'user_id' => $user->getKey(),
                    'event' => $event,
                    'ip_address' => $metadata['ip_address'] ?? null,
                    'metadata' => array_filter(['reason' => $access['block_reason']]),
                ]);
            }

            throw new StreamingBlockedException('Streaming is temporarily blocked.');
        }

        return $access;
    }

    /**
     * @param  array{high_quality_enabled: bool, lossless_enabled: bool}  $access
     * @return array{access_token: string, refresh_token: string, token_type: string, expires_in: int}
     */
    private function tokenResponse(User $user, string $refreshToken, array $access): array
    {
        $now = now();
        $accessTtl = $this->accessTtlMinutes();

        return [
            'access_token' => $this->signAccessToken($user, $now->timestamp, $accessTtl, $access),
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => $accessTtl * 60,
        ];
    }

    /** @param  array{high_quality_enabled: bool, lossless_enabled: bool}  $access */
    private function signAccessToken(User $user, int $issuedAt, int $ttlMinutes, array $access): string
    {
        return $this->signer->sign([
            'sub' => (string) $user->getKey(),
            'iss' => $this->signer->issuer(),
            'aud' => $this->signer->audience(),
            'iat' => $issuedAt,
            'nbf' => $issuedAt,
            'exp' => $issuedAt + ($ttlMinutes * 60),
            'jti' => (string) Str::uuid(),
            'token_use' => 'access',
            'hq' => $access['high_quality_enabled'],
            // The gateway serves the FLAC rendition only with this claim.
            'lossless' => $access['lossless_enabled'],
        ]);
    }

    private function refreshTtlDays(): int
    {
        return $this->boundedConfig('stream.refresh.ttl_days', 1, 90);
    }

    private function accessTtlMinutes(): int
    {
        return $this->boundedConfig('stream.jwt.access_ttl_minutes', 5, 60);
    }

    private function boundedConfig(string $key, int $min, int $max): int
    {
        $value = (int) config($key);

        if ($value < $min || $value > $max) {
            throw new RuntimeException("{$key} must be between {$min} and {$max}.");
        }

        return $value;
    }

    private function newRefreshToken(): string
    {
        return bin2hex(random_bytes(48));
    }

    private function tokenHash(string $token): string
    {
        return hash('sha256', $token);
    }

    private function passwordFingerprint(User $user): string
    {
        return hash('sha256', (string) $user->getAuthPassword());
    }

    private function log(MobileSession $session, string $event, array $metadata = [], ?User $actor = null, array $extra = []): void
    {
        MobileAuthEvent::query()->create([
            'mobile_session_id' => $session->getKey(),
            'user_id' => $session->user_id,
            'actor_user_id' => $actor?->getKey(),
            'event' => $event,
            'ip_address' => $metadata['ip_address'] ?? $session->ip_address,
            'metadata' => array_filter([
                'device_name' => $metadata['device_name'] ?? $session->device_name,
                'platform' => $metadata['platform'] ?? $session->platform,
                ...$extra,
            ], fn ($value) => $value !== null),
        ]);
    }
}
