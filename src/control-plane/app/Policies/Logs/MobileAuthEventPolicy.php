<?php

namespace App\Policies\Logs;

use App\Enums\Roles;
use App\Models\Auth\User;
use App\Models\Logs\MobileAuthEvent;

class MobileAuthEventPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->allowed($user);
    }

    public function view(User $user, MobileAuthEvent $event): bool
    {
        return $this->allowed($user);
    }

    public function delete(User $user, MobileAuthEvent $event): bool
    {
        return false;
    }

    private function allowed(User $user): bool
    {
        return $user->hasRole(Roles::SUPER_ADMIN->value);
    }
}
