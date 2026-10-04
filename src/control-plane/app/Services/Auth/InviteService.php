<?php

namespace App\Services\Auth;

use App\Enums\Roles;
use App\Models\Auth\Invite;
use App\Models\Auth\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class InviteService
{
    /** @return array{invite: Invite, code: string} */
    public function create(User $creator, ?string $email, int $maxUses, int $expiresInDays): array
    {
        if ($email && $maxUses !== 1) {
            throw ValidationException::withMessages(['max_uses' => __('invite.email_single_use')]);
        }
        if (Invite::query()->whereNull('revoked_at')->where('expires_at', '>', now())
            ->whereColumn('uses_count', '<', 'max_uses')->count() >= max(1, (int) config('instance.max_active_invites'))) {
            throw ValidationException::withMessages(['email' => __('invite.limit_reached')]);
        }

        $code = bin2hex(random_bytes(32));
        $invite = Invite::query()->create([
            'code_hash' => hash('sha256', $code),
            'email' => $email ? mb_strtolower(trim($email)) : null,
            'max_uses' => $maxUses,
            'expires_at' => now()->addDays($expiresInDays),
            'created_by_user_id' => $creator->getKey(),
        ]);

        return compact('invite', 'code');
    }

    /** @param array{name: string, email: string, password: string} $attributes */
    public function redeem(string $code, array $attributes, ?string $ipAddress = null): User
    {
        if (config('instance.registration_mode') !== 'invite_only' || ! preg_match('/\A[a-f0-9]{64}\z/', $code)) {
            throw $this->invalidInvite();
        }

        return DB::transaction(function () use ($code, $attributes, $ipAddress): User {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement('SELECT pg_advisory_xact_lock(?)', [129697]);
            }
            $invite = Invite::query()->where('code_hash', hash('sha256', $code))->lockForUpdate()->first();
            $email = mb_strtolower(trim($attributes['email']));

            if (! $invite instanceof Invite || ! $invite->isUsableFor($email)) {
                throw $this->invalidInvite();
            }

            if (User::query()->where('email', $email)->exists()) {
                throw ValidationException::withMessages(['email' => __('invite.account_exists')]);
            }
            if (User::query()->count() >= max(1, (int) config('instance.max_users'))) {
                throw ValidationException::withMessages(['invite_code' => __('invite.capacity_reached')]);
            }

            $user = User::query()->create([
                'name' => $attributes['name'],
                'email' => $email,
                'password' => Hash::make($attributes['password']),
            ]);
            $user->assignRole(Roles::USER->value);
            $invite->increment('uses_count');
            $invite->redemptions()->create(['user_id' => $user->getKey(), 'ip_address' => $ipAddress]);

            return $user;
        });
    }

    private function invalidInvite(): ValidationException
    {
        return ValidationException::withMessages(['invite_code' => __('invite.invalid')]);
    }
}
