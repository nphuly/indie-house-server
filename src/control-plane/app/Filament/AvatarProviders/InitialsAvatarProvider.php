<?php

namespace App\Filament\AvatarProviders;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Draws the admin panel's initials avatar inline. Filament's default loads it
 * from ui-avatars.com, which would hand every admin page view (address and
 * initials) to a third party.
 */
class InitialsAvatarProvider implements AvatarProvider
{
    public function get(Model|Authenticatable $record): string
    {
        $initials = str(Filament::getNameForDefaultAvatar($record))
            ->trim()
            ->explode(' ')
            ->map(fn (string $segment): string => mb_substr((string) preg_replace('/^[^\p{L}\p{N}]+/u', '', $segment), 0, 1))
            ->filter()
            ->take(2)
            ->join('');

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">'
            .'<rect width="64" height="64" fill="#0b0b10"/>'
            .'<text x="32" y="32" dy=".35em" text-anchor="middle" font-family="sans-serif" font-size="26" fill="#ffffff">'
            .htmlspecialchars(mb_strtoupper($initials), ENT_XML1 | ENT_QUOTES, 'UTF-8')
            .'</text></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
