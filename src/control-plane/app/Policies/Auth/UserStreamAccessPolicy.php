<?php

namespace App\Policies\Auth;

use App\Enums\Roles;
use App\Models\Auth\User;
use App\Models\Auth\UserStreamAccess;

class UserStreamAccessPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->allowed($user);
    }

    public function view(User $user, UserStreamAccess $access): bool
    {
        return $this->allowed($user);
    }

    public function create(User $user): bool
    {
        return $this->allowed($user);
    }

    public function update(User $user, UserStreamAccess $access): bool
    {
        return $this->allowed($user);
    }

    public function delete(User $user, UserStreamAccess $access): bool
    {
        return false;
    }

    private function allowed(User $user): bool
    {
        return $user->hasRole(Roles::SUPER_ADMIN->value);
    }
}
