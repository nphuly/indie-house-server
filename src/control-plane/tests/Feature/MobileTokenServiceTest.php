<?php

namespace Tests\Feature;

use App\Models\Auth\MobileSession;
use App\Models\Auth\User;
use App\Services\Auth\MobileTokenService;
use App\Services\Auth\RefreshTokenReplayException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileTokenServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_issues_a_short_lived_rs256_access_token_and_a_hashed_mobile_session(): void
    {
        $keyPath = tempnam(sys_get_temp_dir(), 'indie-house-jwt-');
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $privateKey);
        file_put_contents($keyPath, $privateKey);

        try {
            config([
                'stream.jwt.private_key_path' => $keyPath,
                'stream.jwt.issuer' => 'https://identity.indie-house.test',
                'stream.jwt.audience' => 'indie-house-mobile',
                'stream.jwt.access_ttl_minutes' => 15,
                'stream.refresh.ttl_days' => 30,
            ]);
            $user = User::factory()->create();

            $tokens = app(MobileTokenService::class)->issue($user);
            [$header, $payload, $signature] = explode('.', $tokens['access_token']);
            $claims = json_decode($this->base64UrlDecode($payload), true, flags: JSON_THROW_ON_ERROR);
            $details = openssl_pkey_get_details($key);

            $this->assertSame('Bearer', $tokens['token_type']);
            $this->assertSame(900, $tokens['expires_in']);
            $this->assertMatchesRegularExpression('/\A[a-f0-9]{96}\z/', $tokens['refresh_token']);
            $this->assertSame((string) $user->getKey(), $claims['sub']);
            $this->assertSame('access', $claims['token_use']);
            $this->assertSame('indie-house-mobile', $claims['aud']);
            $this->assertSame(1, openssl_verify("{$header}.{$payload}", $this->base64UrlDecode($signature), $details['key'], OPENSSL_ALGO_SHA256));
            $this->assertDatabaseCount('mobile_sessions', 1);
            $this->assertNotSame($tokens['refresh_token'], MobileSession::query()->sole()->token_hash);
        } finally {
            if (is_file($keyPath)) {
                unlink($keyPath);
            }
        }
    }

    public function test_reusing_the_previous_refresh_token_revokes_the_mobile_session(): void
    {
        $keyPath = tempnam(sys_get_temp_dir(), 'indie-house-jwt-');
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $privateKey);
        file_put_contents($keyPath, $privateKey);

        try {
            config([
                'stream.jwt.private_key_path' => $keyPath,
                'stream.jwt.issuer' => 'https://identity.indie-house.test',
                'stream.jwt.audience' => 'indie-house-mobile',
            ]);
            $user = User::factory()->create();
            $service = app(MobileTokenService::class);
            $firstPair = $service->issue($user);
            $secondPair = $service->refresh($firstPair['refresh_token']);

            $this->assertNotNull($secondPair);

            try {
                $service->refresh($firstPair['refresh_token']);
                $this->fail('A replayed refresh token must revoke the session.');
            } catch (RefreshTokenReplayException) {
                $this->assertNotNull(MobileSession::query()->sole()->fresh()->revoked_at);
                $this->assertDatabaseHas('mobile_auth_events', ['event' => 'refresh_replay_detected']);
            }
        } finally {
            if (is_file($keyPath)) {
                unlink($keyPath);
            }
        }
    }

    private function base64UrlDecode(string $value): string
    {
        return base64_decode(strtr($value, '-_', '+/'), true);
    }
}
