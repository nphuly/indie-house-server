# Privacy and legal pages

**English** · [Tiếng Việt](../vi/privacy.md)

Indie House is built to keep only what the service needs and to use none of it commercially: no selling or sharing of data, no advertising, no analytics. Each instance's data stays with its operator; the authors of the software receive nothing.

## Terms and privacy pages

Every instance serves `/terms` (terms of use) and `/privacy` (privacy policy) in Vietnamese and English. The apps show the same texts natively through `GET /api/v1/mobile/legal/{terms|privacy}`, so editing them never requires a new app release.

- The texts live in `src/control-plane/lang/vi/legal.php` and `lang/en/legal.php`. Both files must have the same keys.
- `:instance` is replaced with `INSTANCE_NAME`. The contact section shows `INSTANCE_CONTACT_EMAIL`, or says to contact the operator who sent the invitation.
- Links appear in the listener app's footer, next to the consent line on the sign-up and invitation forms, in the history section of Settings, and at the bottom of `/legacy`.
- After editing, update the `updated` key in both files.

The shipped texts are written for a small, invited group and describe exactly the data below. They are a starting point, not legal advice. Review them for your jurisdiction; in Vietnam, compare them with Decree 13/2023/ND-CP and the Personal Data Protection Law, especially on consent and deletion requests.

Update the texts whenever you change what is stored or logged, add a third-party service (analytics, CDN, email, crash reporting), change retention, open public registration, or run without Cloudflare.

## What an instance stores

| Data | Where | Purpose | Kept |
| --- | --- | --- | --- |
| Name, email, password hash, profile picture, remember-me token | `users`; pictures in the private bucket under `avatars/{user_id}/` | Sign-in, profile | Until the account is deleted (the picture is deleted with it) |
| Streaming quality, autoplay, whether to record history | `user_preferences` | Playback | Until the account is deleted |
| Playlists and their tracks | `playlists`, `playlist_tracks` | Library | Until the account is deleted |
| Track, seconds listened, completed or not, time | `user_play_logs` (no IP address, no user agent) | Recent plays, history, play counts | No automatic expiry |
| Device name, platform, IP address, user agent, refresh-token hash, last use, expiry | `mobile_sessions` | App sign-in, device revocation | Expires after `STREAM_REFRESH_TTL_DAYS` (30); the row stays for audit |
| App sign-in, refresh and revocation events with IP and device | `mobile_auth_events` | Abuse detection | No automatic expiry |
| Invited email (if any), IP at redemption | `invites`, `invite_redemptions` | Invitation control | No automatic expiry |
| Web session: user, CSRF token, language, password hash HMAC | Redis | Web sign-in | `SESSION_LIFETIME` (120 minutes idle) |
| Rate-limit counters keyed by hashed email and IP | Redis | Brute-force protection | 1–2 minutes |
| Gateway log: user ID, track, timings, first 100 characters of the user agent, no IP | Container output, 5 × 10 MB | Playback quality | Until rotated |
| Edge log: status and duration only | Container output, 5 × 10 MB | Alerts on 401/429/5xx | Until rotated |
| All database data above | Backups, AES-256 encrypted; offsite copy if configured | Recovery | Per the backup retention settings |

On the listener's device:

- **Browser cookies:** the session cookie, `XSRF-TOKEN`, `filament_language_switcher_locale` (the chosen language) and the remember-me cookie when chosen.
- **Browser localStorage:** the current track, volume, shuffle/repeat state, queue panel state and recent searches.
- **iOS:** tokens in the Keychain; server address, connection profile, recent searches and preferences in `UserDefaults`.
- **Android:** tokens and preferences in app storage, with backups disabled.

## Third parties

With the default setup:

- **Cloudflare** (tunnel and proxy) sees visitors' IP addresses and traffic.
- **Object storage** is whatever the operator chooses.
- **Bunny Fonts** serves the admin panel's font. Listener pages call no outside service.

## Listener controls

In **Settings**, listeners can change their name, email (with their current password) and picture, turn off history recording, change their password, revoke app sessions, and download their data as JSON. There is no self-service account deletion yet: the operator deletes accounts on request, which also deletes the profile picture.
