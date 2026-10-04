<?php

namespace App\Services\Media;

use App\Models\Media\Track;
use App\Services\Auth\StreamJwtSigner;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * A playback ticket lets a browser stream through the gateway. It is an RS256
 * JWT for one track at one quality, signed with the mobile token keys and
 * carried in the audio URL, because an <audio> element cannot send a bearer
 * header. The gateway accepts it only on /v1/play/{ticket}; the mobile API
 * and the gateway's bearer routes refuse it (`token_use` is "playback").
 */
class PlaybackTicketService
{
    public function __construct(private readonly StreamJwtSigner $signer) {}

    public function url(Track $track, string $quality, string $subject): string
    {
        return url('/api/v1/stream/v1/play/'.$this->issue($track, $quality, $subject));
    }

    public function issue(Track $track, string $quality, string $subject): string
    {
        if (! in_array($quality, PlaybackRenditions::QUALITIES, true)) {
            throw new RuntimeException("Unknown playback quality {$quality}.");
        }
        $now = now()->timestamp;

        return $this->signer->sign([
            'sub' => $subject,
            'iss' => $this->signer->issuer(),
            'aud' => $this->signer->audience(),
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + $this->ttlMinutes() * 60,
            'jti' => (string) Str::uuid(),
            'token_use' => 'playback',
            'trk' => $track->public_id,
            'q' => $quality,
        ]);
    }

    /**
     * The gateway rate-limits per subject. Guests share no account, so each
     * browser session gets its own subject without revealing the session ID.
     */
    public static function subjectFor(Request $request): string
    {
        $user = $request->user();
        if ($user !== null) {
            return (string) $user->getKey();
        }

        return 'guest:'.substr(hash_hmac('sha256', $request->session()->getId(), (string) config('app.key')), 0, 16);
    }

    private function ttlMinutes(): int
    {
        $minutes = (int) config('stream.playback_ticket_ttl_minutes');

        if ($minutes < 5 || $minutes > 360) {
            throw new RuntimeException('stream.playback_ticket_ttl_minutes must be between 5 and 360.');
        }

        return $minutes;
    }
}
