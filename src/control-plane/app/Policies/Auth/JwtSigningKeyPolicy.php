<?php

namespace App\Policies\Auth;

use App\Enums\Roles;
use App\Models\Auth\JwtSigningKey;
use App\Models\Auth\User;

class JwtSigningKeyPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->allowed($user);
    }

    public function view(User $user, JwtSigningKey $key): bool
    {
        return $this->allowed($user);
    }

    public function create(User $user): bool
    {
        return $this->allowed($user);
    }

    public function update(User $user, JwtSigningKey $key): bool
    {
        return $this->allowed($user);
    }

    public function delete(User $user, JwtSigningKey $key): bool
    {
        return false;
    }

    private function allowed(User $user): bool
    {
        return $user->hasRole(Roles::SUPER_ADMIN->value);
    }
}
