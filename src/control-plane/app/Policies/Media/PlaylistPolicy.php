<?php

namespace App\Policies\Media;

use App\Models\Auth\User;
use App\Models\Media\Playlist;

class PlaylistPolicy
{
    public function view(?User $user, Playlist $playlist): bool
    {
        return $playlist->is_public || $user?->id === $playlist->user_id;
    }

    public function update(User $user, Playlist $playlist): bool
    {
        return $user->id === $playlist->user_id;
    }

    public function delete(User $user, Playlist $playlist): bool
    {
        return $this->update($user, $playlist);
    }
}
