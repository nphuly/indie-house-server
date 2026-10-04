<?php

namespace App\Http\Middleware;

use App\Models\Auth\JwtSigningKey;
use App\Models\Auth\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateMobileAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        $claims = is_string($token) && strlen($token) <= 8192 ? $this->claims($token) : null;
        $user = is_array($claims) && ctype_digit((string) ($claims['sub'] ?? ''))
            ? User::query()->find((int) $claims['sub'])
            : null;

        if (! $user instanceof User) {
            return response()->json(['message' => 'Invalid or expired bearer token.'], 401, ['Cache-Control' => 'no-store']);
        }

        $request->setUserResolver(fn (): User => $user);

        return $next($request);
    }

    /** @return array<string, mixed>|null */
    private function claims(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;
        $header = $this->decodeJson($encodedHeader);
        $claims = $this->decodeJson($encodedPayload);
        $signature = $this->decode($encodedSignature);
        $kid = $header['kid'] ?? null;

        if (! is_array($header) || ! is_array($claims) || ! is_string($kid)
            || ! preg_match('/\A[a-zA-Z0-9._-]{1,120}\z/', $kid)
            || ($header['alg'] ?? null) !== 'RS256' || $signature === null) {
            return null;
        }

        $path = $kid === config('stream.jwt.key_id')
            ? config('stream.jwt.private_key_path')
            : (JwtSigningKey::query()->where('kid', $kid)->exists()
                ? rtrim((string) config('stream.jwt.managed_keys_directory'), '/')."/{$kid}.private.pem"
                : null);
        if (! is_string($path) || $path === '') {
            return null;
        }

        $contents = @file_get_contents($path);
        $privateKey = $contents === false ? false : openssl_pkey_get_private($contents);
        $publicKey = $privateKey === false ? null : (openssl_pkey_get_details($privateKey)['key'] ?? null);
        if (! is_string($publicKey) || openssl_verify("{$encodedHeader}.{$encodedPayload}", $signature, $publicKey, OPENSSL_ALGO_SHA256) !== 1) {
            return null;
        }

        $now = now()->timestamp;
        $audience = $claims['aud'] ?? null;
        $validAudience = is_string($audience)
            ? $audience === config('stream.jwt.audience')
            : (is_array($audience) && in_array(config('stream.jwt.audience'), $audience, true));

        if (($claims['token_use'] ?? null) !== 'access'
            || ($claims['iss'] ?? null) !== config('stream.jwt.issuer')
            || ! $validAudience
            || ! isset($claims['exp'], $claims['nbf'], $claims['iat'])
            || ! is_int($claims['exp']) || ! is_int($claims['nbf']) || ! is_int($claims['iat'])
            || $claims['exp'] <= $now || $claims['nbf'] > $now || $claims['iat'] > $now
            || $claims['exp'] - $claims['iat'] > 3600) {
            return null;
        }

        return $claims;
    }

    /** @return array<string, mixed>|null */
    private function decodeJson(string $value): ?array
    {
        $decoded = $this->decode($value);
        $json = $decoded === null ? null : json_decode($decoded, true);

        return is_array($json) ? $json : null;
    }

    private function decode(string $value): ?string
    {
        if ($value === '' || ! preg_match('/\A[A-Za-z0-9_-]+\z/', $value)) {
            return null;
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }
}
