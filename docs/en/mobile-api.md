# Mobile API

**English** · [Tiếng Việt](../vi/mobile-api.md)

The [iOS](https://github.com/nphuly/indie-house-ios) and [Android](https://github.com/nphuly/indie-house-android) apps use a versioned JSON API. Any client for Indie House can use it the same way. Requests and responses are JSON; send `Accept-Language` (`vi` or `en`) to get server messages in the app's language.

## 1. Discover the instance

The user enters the instance's HTTPS address or scans the QR code shown in the admin panel. The client then fetches:

```http
GET https://example.com/.well-known/indie-house
```

```json
{
  "schema_version": 1,
  "instance_id": "0d6f4afc-6a0e-4c5b-9a4e-2f1c9e1b7a10",
  "display_name": "Indie House",
  "api_base": "https://example.com/api/v1/mobile",
  "stream_base": "https://example.com/api/v1/stream",
  "jwt_issuer": "https://example.com",
  "registration_mode": "invite_only",
  "minimum_mobile_version": "1.0.0"
}
```

Client rules:

1. Accept `https` only. Do not follow a redirect to another host, and reject a manifest whose endpoints point to a host the user has not confirmed.
2. Show the instance name and hostname, and let the user confirm them before sending a password.
3. After confirmation, store a profile (`instance_id`, base URL, confirmation time) and pin the `instance_id` to the host. If the host later reports a different ID, warn the user and ask again.
4. Send tokens only to the confirmed origin. Removing a profile deletes its tokens; never reuse tokens across instances.
5. Use the platform's normal TLS validation.

## 2. Sign in

All auth routes are under `/api/v1/mobile/auth`.

```http
POST /api/v1/mobile/auth/token
{"email": "listener@example.com", "password": "…", "device_name": "iPhone", "platform": "ios"}
```

```json
{"access_token": "eyJ…", "refresh_token": "96 hex characters", "token_type": "Bearer", "expires_in": 900}
```

| Route | Body | Result |
| --- | --- | --- |
| `POST /token` | `email`, `password`, optional `device_name`, `platform` | `200` with a token pair, `401` on wrong credentials |
| `POST /refresh` | `refresh_token`, optional `device_name`, `platform` | `200` with a **new** pair; the old refresh token stops working |
| `POST /logout` | `refresh_token` | `204`, idempotent |

Auth responses carry `Cache-Control: no-store`.

- The **access token** is an RS256 JWT, valid for 15 minutes by default, with `token_use: access`. Send it as `Authorization: Bearer <token>`.
- The **refresh token** is an opaque random string. The server stores only its hash. It lasts 30 days by default and rotates on every refresh.
- **Reusing an old refresh token revokes the whole session** and is recorded in the audit log. After a refresh, store the new pair atomically before retrying the request.
- On a `401`, let only one refresh run at a time. If the refresh fails, delete the stored tokens and ask the user to sign in.
- Keep tokens in the Keychain (iOS) or an Android Keystore-wrapped store. Never log them.

Access token claims: `sub` (user ID), `iss`, `aud`, `iat`, `nbf`, `exp`, `jti`, `token_use`, `hq`, and `lossless` when the user has lossless access. The header carries the `kid` of the signing key.

### Joining with an invite

When `registration_mode` is `invite_only`:

```http
POST /api/v1/mobile/invites/redeem
{"invite_code": "…64 characters…", "name": "…", "email": "…", "password": "…", "password_confirmation": "…"}
```

The account is created; then sign in with `/auth/token`.

## 3. Endpoints

All routes below are under `/api/v1/mobile` and need a valid access token, except `/legal/*`. Media is always addressed by its `public_id`.

| Method | Path | Returns |
| --- | --- | --- |
| GET | `/catalogue?page=N` | Published tracks, most played first, 20 per page, with `meta` |
| GET | `/browse` | Counts, the 20 newest tracks, 12 newest albums, 12 most-played artists |
| GET | `/albums?page=N` | Albums with public tracks, newest first, 24 per page |
| GET | `/artists?page=N` | Artists with public tracks, A–Z, 24 per page |
| GET | `/search?q=…` | Matching tracks, artists and albums |
| GET | `/tracks/{id}` | Track details, including `stream_path` |
| GET | `/artists/{id}`, `/albums/{id}` | The artist or album with its public tracks in order |
| GET | `/tracks/{id}/artwork`, `/artists/{id}/artwork`, `/albums/{id}/artwork` | Image bytes (Bearer required) |
| GET | `/library` | The user's playlists and up to 30 recent tracks, each once |
| GET, POST | `/playlists` | List, or create with `title` (2–120 chars), `is_public`, optional `description` → `201` |
| GET, PATCH, DELETE | `/playlists/{id}` | Read, update or delete. Only the owner can change a playlist; public playlists are readable by others |
| POST | `/playlists/{id}/tracks` | Add `track_id` → `204` |
| DELETE | `/playlists/{id}/tracks/{trackId}` | Remove a track → `204` |
| POST | `/play-logs` | Record a play: `track_id`, `played_duration_seconds`, `completed`, `playback_id` → `201`, or `204` when it was already recorded or history is off |
| GET | `/devices` | The user's mobile sessions |
| DELETE | `/devices/{sessionId}` | Revoke one of them |
| GET, PATCH | `/preferences` | `audio_quality`, `autoplay`, `record_listening_history`, and read-only `lossless_available` |
| GET | `/account` | `name`, `email`, `avatar_url` (or `null`) |
| GET | `/account/avatar/{version}` | The user's picture; `version` changes when the picture does |
| GET | `/legal/terms`, `/legal/privacy` | **No token.** The instance's terms or privacy policy as structured JSON, in the language from `Accept-Language` |

Every catalogue response contains only published, fully processed tracks. `artwork_url` and `avatar_url` values point to the routes above and also need the Bearer header.

`audio_quality` accepts `auto`, `standard`, `high`, and `lossless` only when `lossless_available` is `true`; otherwise `lossless` is rejected with `422`. See [Playback](playback.md#quality-choices).

## 4. Playing audio

`stream_path` is `/api/v1/stream/v1/tracks/{id}/stream` on the same origin. Request it with the access token and an optional `quality` (`auto`, `standard`, `high`, `lossless`). The gateway supports single-range `Range` requests for seeking. Other gateway routes are listed in [Playback](playback.md#app-playback).

## Errors and limits

| Status | Meaning |
| --- | --- |
| `401` | Missing, expired or invalid token, or wrong credentials |
| `403` / `404` | Not allowed, or not found (unpublished media looks like it does not exist) |
| `422` | Validation error, with Laravel's `errors` object |
| `429` | Rate limited |
| `503` | Manifest: the instance is not fully configured |

Rate limits: 120 requests per minute per user on authenticated routes; sign-in limited per email and per IP; refresh and logout 30 per minute per IP; invite redemption 5 per minute; legal documents 60 per minute. The gateway applies its own per-user limits.
