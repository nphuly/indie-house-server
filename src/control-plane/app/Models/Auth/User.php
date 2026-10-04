<?php

namespace App\Models\Auth;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Concerns\CleansS3Media;
use App\Enums\Roles;
use App\Models\Logs\MobileAuthEvent;
use App\Models\Logs\UserPlayLog;
use App\Models\Media\Playlist;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use CleansS3Media, HasFactory, HasRoles, Notifiable;

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    public function playLogs(): HasMany
    {
        return $this->hasMany(UserPlayLog::class);
    }

    public function playlists(): HasMany
    {
        return $this->hasMany(Playlist::class);
    }

    public function mobileSessions(): HasMany
    {
        return $this->hasMany(MobileSession::class);
    }

    public function preference(): HasOne
    {
        return $this->hasOne(UserPreference::class);
    }

    public function streamAccess(): HasOne
    {
        return $this->hasOne(UserStreamAccess::class);
    }

    public function mobileAuthEvents(): HasMany
    {
        return $this->hasMany(MobileAuthEvent::class);
    }

    /**
     * A signed link to the profile picture in the private bucket. It expires,
     * so render it with the page and never store it.
     */
    public function avatarUrl(): ?string
    {
        return $this->avatar_path ? Storage::disk('s3')->temporaryUrl($this->avatar_path, now()->addMinutes(30)) : null;
    }

    /** Changes whenever the picture does, so the apps drop a cached copy. */
    public function avatarVersion(): ?string
    {
        return $this->avatar_path ? substr(hash('sha256', $this->avatar_path), 0, 12) : null;
    }

    /** @return array<int, string> */
    public function mediaPathsForDeletion(): array
    {
        return array_filter([$this->avatar_path]);
    }

    /** @return array<int, string> */
    public function mediaDirectoriesForDeletion(): array
    {
        return [];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return match ($panel->getId()) {
            'admin' => $this->hasAnyRole([
                Roles::SUPER_ADMIN->value,
                Roles::ADMIN->value,
                Roles::STAFF->value,
            ]),
            default => false,
        };
    }
}
