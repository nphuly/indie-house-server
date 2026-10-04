<?php

namespace App\Policies\Auth;

use App\Enums\Roles;
use App\Models\Auth\MobileSession;
use App\Models\Auth\User;

class MobileSessionPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->allowed($user);
    }

    public function view(User $user, MobileSession $session): bool
    {
        return $this->allowed($user);
    }

    public function update(User $user, MobileSession $session): bool
    {
        return $this->allowed($user);
    }

    public function delete(User $user, MobileSession $session): bool
    {
        return false;
    }

    private function allowed(User $user): bool
    {
        return $user->hasRole(Roles::SUPER_ADMIN->value);
    }
}
