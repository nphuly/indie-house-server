# Security

**English** · [Tiếng Việt](../vi/security.md)

This page describes how Indie House protects an instance, what it leaves to the operator, and the known limits.

## Reporting a vulnerability

Please do not open a public issue. Use **Security → Report a vulnerability** on the GitHub repository to send a private report. If you run an instance and suspect it has been compromised, contact its operator.

## Trust boundaries

```text
Internet ──▶ HTTPS proxy ──▶ edge (loopback only) ──▶ control-plane ──▶ PostgreSQL, Redis, storage
                                     │                     ▲
                                     └──▶ stream-service ──┘ internal API (Compose network only)
```

- Only the edge publishes a port, on `127.0.0.1`. PostgreSQL, Redis and the internal API are reachable only on the private Compose network.
- The edge answers `404` for `/api/internal/*`, including encoded and case variants of the path.
- The edge sets the client address from the proxy's header and overwrites `X-Forwarded-For` and `X-Forwarded-Proto`. Laravel trusts only those two headers, never a forwarded host.
- The Go services receive only the environment variables they need. They never get `APP_KEY` or database credentials, and the gateway never gets a private key.

## Authentication

| Surface | Mechanism |
| --- | --- |
| Web | Laravel session cookie (`Secure` in production). Changing a password ends every other web session and "remember me" cookie. Post-login redirects only accept same-host URLs. |
| Mobile API | RS256 access JWT (15 minutes) and an opaque refresh token (30 days) that rotates on every use and is stored hashed. Reusing a previous refresh token revokes the session. |
| Gateway | The same access JWT, or a web playback ticket bound to one track and one quality. `token_use` keeps the two apart. |
| Internal API | `X-Internal-Key`, compared against `INTERNAL_API_KEY`. An empty key refuses every request. |

JWT verification accepts only `RS256` with a well-formed `kid`, and checks `iss`, `aud`, `exp` and `nbf` on both the control plane and the gateway. Signing keys can be rotated without downtime; see [Operations](operations.md#rotating-signing-keys).

**Rate limits** count per visitor because the client IP comes from the proxy:

- web sign-in: 5 per minute per email and 30 per minute per IP;
- mobile sign-in: per email and per IP; refresh and logout: 30 per minute per IP;
- registration: 10 per minute; invite redemption: 5 per minute;
- gateway: per user and route, failing closed if Redis is unavailable.

**Authorisation.** Playlists go through a policy (only owners change them), device revocation checks ownership, and the data export contains only the requesting user's data. Every listener query uses the public-catalogue scope, so unpublished or unfinished tracks are never exposed. Admin areas are protected by roles; the mobile security pages are visible to super admins only.

## Browser hardening

- **Content-Security-Policy.** Listener, auth and `/legacy` pages only run same-origin scripts that carry the request's nonce: no `unsafe-inline`, no `unsafe-eval`. The admin panel needs inline scripts and `eval` for Filament and Alpine but still loads scripts only from its own origin. Both policies set `object-src 'none'`, `base-uri 'self'`, `form-action 'self'` and `frame-ancestors 'self'`.
- **Other headers:** `Strict-Transport-Security: max-age=31536000` on HTTPS, `X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`.
- No raw storage key or presigned URL is ever given to the browser. See [Playback](playback.md).

## Input handling

- Track uploads are limited by type and size and protected against path tampering. Profile pictures accept JPEG, PNG or WebP only.
- The media worker accepts only UUID track IDs and a fixed list of audio extensions, and runs `ffmpeg`/`ffprobe` with `-protocol_whitelist file`, so a crafted file cannot make FFmpeg read other files or URLs.
- Raw SQL always uses bound parameters; search column names come from a fixed list.
- Backup restore checks every path in the archive before extracting it, and backup downloads require the backup page permission.

## Data protection

- Passwords are hashed with bcrypt. Refresh tokens and invite codes are stored only as hashes.
- Redis requires a password in production.
- Backups are AES-256 encrypted; the deploy script refuses to run without a key.
- Play history stores no IP address or user agent. The gateway log contains no IP address, token or object key, and the edge log drops the request entirely.
- The listener pages call no third-party service. See [Privacy](privacy.md) for what is stored.

## Clients

- **iOS** stores tokens in the Keychain with `AfterFirstUnlockThisDeviceOnly`, requires HTTPS, and sends tokens only to the confirmed origin.
- **Android (API 18)** stores tokens encrypted with an Android Keystore key when the device supports it, otherwise only in memory. Backups are disabled (`allowBackup="false"`). It validates certificate chains and hostnames, and bundles the ISRG Root X1 certificate as an additional trusted root because Android 4.3 lacks it.

## Known limitations

These are accepted trade-offs or work left to the operator:

1. **Tokens live until they expire.** After a session is revoked or a user blocked, an access token keeps working for up to 15 minutes and a playback ticket for up to `STREAM_PLAYBACK_TICKET_TTL_MINUTES` (60).
2. **Retired signing keys** are accepted as long as their files are mounted. Remove them after a rotation.
3. **The admin CSP is weaker** (`unsafe-inline`, `unsafe-eval`), as Filament requires. Only admins see those pages.
4. **HSTS covers the main hostname only.** Enable it for subdomains at your proxy once every subdomain serves HTTPS.
5. **The database user is the PostgreSQL superuser** of the bundled container. A dedicated role (which still needs `CREATE EXTENSION` during migrations) is left to the operator.
6. **Password rules are minimal** (8 characters) and there is no breached-password check.
7. **Sign-in timing** differs slightly for unknown emails; the per-email rate limit slows enumeration.
8. **Play counts trust the client's `completed` flag.** Duplicates are ignored per playback, and logging is rate-limited, but a user could still inflate counts.
9. **FrankenPHP runs as root** inside the control-plane container.
10. **The admin panel loads its font from Bunny Fonts**, which is allowed in its CSP.
11. **One internal API key** is shared by the media worker and the gateway.
12. **File names inside backups are not encrypted**, only their contents.
13. **No DRM.** Anyone who can play a track can record it; see [Playback](playback.md#limits-of-protection).

## Operator checklist

- [ ] `APP_DEBUG=false`, a strong `APP_KEY`, and no secret in Git.
- [ ] `APP_URL`, `INSTANCE_BASE_URL` and `STREAM_JWT_ISSUER` are the same HTTPS origin.
- [ ] The private signing key is readable only by the deploy account and backed up encrypted; the gateway has public keys only.
- [ ] The edge stays on `127.0.0.1`; PostgreSQL, Redis, storage console and internal API are not exposed.
- [ ] The storage bucket is private and the application uses credentials limited to it.
- [ ] `REDIS_PASSWORD` and `BACKUP_ENCRYPTION_KEY` are set, and the backup key is stored off the server.
- [ ] Registration is `invite_only` or `closed`, and each person has their own account.
- [ ] Monitoring alerts on `instance:health` and on bursts of `401`, `429` and `5xx`.
- [ ] `https://example.com/api/internal/tracks/status` returns `404`, and the CSP and HSTS headers are present.

## Regular checks

```bash
cd src/control-plane && composer audit --locked && npm audit --omit=dev
cd src/stream-service && GOTOOLCHAIN=go1.27.1 go run golang.org/x/vuln/cmd/govulncheck@latest ./...
cd src/media-worker && GOTOOLCHAIN=go1.27.1 go run golang.org/x/vuln/cmd/govulncheck@latest ./...
```

Run `govulncheck` with the same Go version as the images; an older local Go may report standard-library issues that are not in the build.
