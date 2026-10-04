<?php

namespace App\Services\Auth;

use App\Models\Auth\JwtSigningKey;
use App\Models\Auth\User;
use App\Models\Logs\MobileAuthEvent;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class JwtSigningKeyManager
{
    public function activate(JwtSigningKey $key, User $actor): void
    {
        $actualFingerprint = $this->privateKeyFingerprint($key);

        if (! hash_equals($key->public_key_fingerprint, $actualFingerprint)) {
            throw new RuntimeException('The mounted private key does not match the registered public-key fingerprint.');
        }

        DB::transaction(function () use ($key, $actor): void {
            JwtSigningKey::query()
                ->active()
                ->whereKeyNot($key->getKey())
                ->update([
                    'status' => JwtSigningKey::RETIRED,
                    'retired_at' => now(),
                ]);

            $key->forceFill([
                'status' => JwtSigningKey::ACTIVE,
                'activated_at' => now(),
                'retired_at' => null,
            ])->save();

            MobileAuthEvent::query()->create([
                'actor_user_id' => $actor->getKey(),
                'event' => 'jwt_key_activated',
                'metadata' => ['kid' => $key->kid],
            ]);
        });
    }

    private function privateKeyFingerprint(JwtSigningKey $key): string
    {
        $path = rtrim((string) config('stream.jwt.managed_keys_directory'), '/')."/{$key->kid}.private.pem";
        $contents = @file_get_contents($path);
        $privateKey = $contents === false ? false : openssl_pkey_get_private($contents);

        if ($privateKey === false) {
            throw new RuntimeException('The private key for this kid is not mounted in the control plane.');
        }

        $details = openssl_pkey_get_details($privateKey);
        $publicPem = $details['key'] ?? null;

        if (! is_string($publicPem)) {
            throw new RuntimeException('Unable to read the public component of the private key.');
        }

        $der = base64_decode(preg_replace('/-----(BEGIN|END) PUBLIC KEY-----|\s/', '', $publicPem), true);

        if ($der === false) {
            throw new RuntimeException('Unable to encode the public-key fingerprint.');
        }

        return hash('sha256', $der);
    }
}
