<?php

namespace App\Services\Auth;

use App\Models\Auth\JwtSigningKey;
use RuntimeException;

/**
 * Signs the RS256 tokens the stream gateway verifies with public keys only:
 * mobile access tokens and playback tickets. The newest active managed key
 * signs; without one, the legacy key does.
 */
class StreamJwtSigner
{
    /** @param  array<string, mixed>  $claims */
    public function sign(array $claims): string
    {
        $signingKey = $this->signingKey();
        $header = $this->base64UrlEncode(json_encode([
            'alg' => 'RS256',
            'typ' => 'JWT',
            'kid' => $signingKey['kid'],
        ], JSON_THROW_ON_ERROR));
        $payload = $this->base64UrlEncode(json_encode($claims, JSON_THROW_ON_ERROR));
        $signingInput = "{$header}.{$payload}";
        $privateKey = $this->privateKey($signingKey['path']);

        if (! openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Unable to sign stream token.');
        }

        return "{$signingInput}.{$this->base64UrlEncode($signature)}";
    }

    public function issuer(): string
    {
        return $this->requiredConfig('stream.jwt.issuer');
    }

    public function audience(): string
    {
        return $this->requiredConfig('stream.jwt.audience');
    }

    /** @return array{kid: string, path: string} */
    private function signingKey(): array
    {
        $key = JwtSigningKey::query()->active()->latest('activated_at')->first();

        if ($key instanceof JwtSigningKey) {
            return [
                'kid' => $key->kid,
                'path' => rtrim((string) config('stream.jwt.managed_keys_directory'), '/')."/{$key->kid}.private.pem",
            ];
        }

        return [
            'kid' => (string) config('stream.jwt.key_id'),
            'path' => $this->requiredConfig('stream.jwt.private_key_path'),
        ];
    }

    /** @return \OpenSSLAsymmetricKey */
    private function privateKey(string $path)
    {
        $contents = @file_get_contents($path);
        $key = $contents === false ? false : openssl_pkey_get_private($contents);

        if ($key === false) {
            throw new RuntimeException('Mobile JWT signing key is unavailable.');
        }

        return $key;
    }

    private function requiredConfig(string $key): string
    {
        $value = config($key);

        if (! is_string($value) || $value === '') {
            throw new RuntimeException("Missing {$key} configuration.");
        }

        return $value;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
