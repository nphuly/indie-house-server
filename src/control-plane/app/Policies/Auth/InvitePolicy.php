<?php

namespace App\Policies\Auth;

use App\Enums\Roles;
use App\Models\Auth\Invite;
use App\Models\Auth\User;

class InvitePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(Roles::SUPER_ADMIN->value);
    }

    public function view(User $user, Invite $invite): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Invite $invite): bool
    {
        return false;
    }

    public function delete(User $user, Invite $invite): bool
    {
        return false;
    }
}
