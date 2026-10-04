# Configuration

**English** · [Tiếng Việt](../vi/configuration.md)

All services read one `.env` file in the repository root. Start from [`.env.example`](../../.env.example), keep the file out of Git (`chmod 600 .env`), and generate every secret separately for each instance and each environment.

```bash
test ! -e .env && cp .env.example .env
chmod 600 .env
```

The Go services do not read `.env` directly. `docker-compose.yml` passes each of them only the variables it needs, under its own names (listed [at the end](#gateway-and-worker-variables)).

## Generating values

| Value | Command |
| --- | --- |
| `APP_KEY` | `printf 'base64:%s\n' "$(openssl rand -base64 32)"` |
| `INSTANCE_ID` | `python3 -c 'import uuid; print(uuid.uuid4())'` |
| `INTERNAL_API_KEY`, `REDIS_PASSWORD` | `openssl rand -hex 32` |
| `BACKUP_ENCRYPTION_KEY` | `openssl rand -base64 32` |
| Signing key pair | See [Signing keys](#signing-keys) |

Generate `APP_KEY`, `INSTANCE_ID` and the signing keys **once** and back them up. Changing them on a running instance logs everyone out, makes mobile apps treat the instance as a new one, or makes encrypted data unreadable.

## Application and instance

| Variable | Default | Notes |
| --- | --- | --- |
| `APP_ENV`, `APP_DEBUG` | `local`, `true` | Production must use `production` and `false`; the deploy script refuses anything else. |
| `APP_URL` | — | The public HTTPS origin, e.g. `https://example.com`. No path or trailing slash. |
| `APP_LOCALE`, `APP_FALLBACK_LOCALE` | `vi`, `en` | Default interface language and fallback. |
| `INSTANCE_ID` | — | UUID v4, generated once. Apps pin it to the hostname; a different ID looks like a different library. |
| `INSTANCE_NAME` | `APP_NAME` | Shown to users before they sign in and in the legal pages. |
| `INSTANCE_BASE_URL` | `APP_URL` | Must equal `APP_URL`. |
| `INSTANCE_CONTACT_EMAIL` | empty | Shown in `/terms` and `/privacy` for data requests and copyright complaints. |
| `INSTANCE_REGISTRATION_MODE` | `closed` | `closed` (no sign-up or invitations), `invite_only` (an invite code is required) or `open` (public sign-up; only after adding your own anti-abuse measures). |
| `INSTANCE_MAX_USERS`, `INSTANCE_MAX_ACTIVE_INVITES` | `100`, `20` | Caps for accounts and unused invites. |
| `INSTANCE_MINIMUM_MOBILE_VERSION` | `1.0.0` | Published in the instance manifest. |

The manifest at `/.well-known/indie-house` answers `503` until `INSTANCE_ID` is a UUID, `INSTANCE_BASE_URL` is an `https` URL and the JWT issuer equals it.

## Proxy and HTTPS

| Variable | Production value | Why |
| --- | --- | --- |
| `PUBLIC_SCHEME` | `https` | The edge sets `X-Forwarded-Proto` from it, so clients cannot choose the scheme. |
| `TRUSTED_PROXIES` | `*` | Laravel trusts the edge on the private Compose network. Never publish the control plane's port. |
| `SESSION_SECURE_COOKIE` | `true` | Session cookies are sent over HTTPS only. |
| `EDGE_BIND_ADDRESS`, `EDGE_PORT` | `127.0.0.1`, `8080` | The edge must stay on loopback: anything that can reach it directly can forge the client IP header. |

## Database

| Variable | Notes |
| --- | --- |
| `DB_CONNECTION`, `DB_HOST`, `DB_PORT` | `pgsql`, `postgres`, `5432` with the bundled container. The deploy script requires these values. |
| `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Compose creates the PostgreSQL container with them. Leave `DB_URL` unset. |

The search migration creates the `unaccent` and `pg_trgm` extensions, so the database role needs `CREATE EXTENSION` while migrating.

## Redis

| Variable | Notes |
| --- | --- |
| `REDIS_HOST`, `REDIS_PORT` | `redis`, `6379` (the Compose service name, not `127.0.0.1`). |
| `REDIS_PASSWORD` | Required in production, at least 32 characters. When set, Compose starts Redis with `requirepass` and every client uses it. Empty in development. |
| `QUEUE_CONNECTION`, `CACHE_STORE`, `SESSION_DRIVER` | `redis` |
| `REDIS_QUEUE_RETRY_AFTER` | `630`. Must be longer than the queue worker's `--timeout=600`, or a slow job is picked up twice. |
| `SESSION_LIFETIME` | Minutes of inactivity before a web session ends (default `120`). |

Mobile refresh sessions are stored in PostgreSQL, not Redis.

## Object storage

Laravel and the Go services use different variable formats:

| Variable | Used by | Format |
| --- | --- | --- |
| `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_BUCKET`, `AWS_DEFAULT_REGION` | all | Credentials limited to the one bucket. Never use storage root credentials. |
| `AWS_ENDPOINT` | Laravel | Full URL clients can reach, e.g. `https://objects.example.com`. Browser uploads and artwork URLs are signed for this host. |
| `AWS_INTERNAL_ENDPOINT` | Laravel | Optional full URL on the local network, e.g. `http://10.0.0.5:9000`. Laravel's own S3 calls go there, still signed for `AWS_ENDPOINT`. |
| `AWS_URL` | Laravel | Base for plain URLs; include the bucket with path-style addressing. |
| `AWS_USE_PATH_STYLE_ENDPOINT` | Laravel | `true` for MinIO. |
| `MINIO_ENDPOINT`, `MINIO_USE_SSL` | Go services | `host:port` **without** a scheme, plus `true`/`false`. Use the local network address so audio does not leave the host and come back. |
| `MINIO_PUBLIC_ENDPOINT`, `MINIO_PUBLIC_USE_SSL` | gateway | The public `host[:port]` for presigned URLs. Empty means `MINIO_ENDPOINT`. |

If storage sits behind Cloudflare, add a Cache Rule that **bypasses the cache** for the storage hostname. Otherwise Cloudflare turns the first `HEAD` on a new `.flac` or `.jpg` object into a `GET`, the S3 signature no longer matches, storage answers `403`, and Laravel treats the file as missing.

## Signing keys

Access tokens and playback tickets are RS256 JWTs. The control plane holds the private key; the gateway gets only public keys.

```bash
openssl genpkey -algorithm RSA -pkeyopt rsa_keygen_bits:3072 -out infrastructure/stream-keys/private.key
openssl pkey -in infrastructure/stream-keys/private.key -pubout -out infrastructure/stream-keys/public.key
chmod 600 infrastructure/stream-keys/private.key
```

| Variable | Default | Notes |
| --- | --- | --- |
| `STREAM_JWT_PRIVATE_KEY_PATH` | `/app/storage/keys/private.key` | Path **inside** the container; Compose mounts `infrastructure/stream-keys` there read-only. |
| `STREAM_JWT_KEY_ID` | `stream-v1` | `kid` of that key. |
| `STREAM_JWT_MANAGED_KEYS_DIRECTORY` | `/app/storage/keys` | Where keys added from the admin panel live, as `<kid>.private.pem`. Their public halves go in `infrastructure/stream-public-keys/<kid>.public.pem`. |
| `STREAM_JWT_ISSUER` | — | Must equal `APP_URL` exactly. Changing it invalidates every token. |
| `STREAM_JWT_AUDIENCE` | `indie-house-mobile` | Must match the gateway's audience; Compose passes the same value. |

Key rotation is described in [Operations](operations.md#rotating-signing-keys).

## Streaming and sessions

| Variable | Default | Notes |
| --- | --- | --- |
| `INTERNAL_API_KEY` | — | Shared by the control plane, media worker and gateway for `/api/internal/*`. The control plane refuses every internal request while it is empty. |
| `STREAM_JWT_ACCESS_TTL_MINUTES` | `15` | Lifetime of a mobile access token (60 at most). |
| `STREAM_REFRESH_TTL_DAYS` | `30` | Lifetime of a mobile refresh session. |
| `STREAM_MAX_SESSIONS_PER_USER` | `10` | Active mobile sessions per account. |
| `STREAM_AUTH_RATE_LIMIT_STORE` | `redis` | Store for mobile sign-in rate limits. |
| `STREAM_DEFAULT_HQ_ENABLED` | `true` | Value of the `hq` claim for users without a stream policy. |
| `STREAM_WEB_AUDIO_VIA_GATEWAY` | `true` | Web audio redirects to the gateway with a playback ticket. Development sets `false`. |
| `STREAM_PLAYBACK_TICKET_TTL_MINUTES` | `60` | 5–360. A leaked ticket plays one track at one quality until it expires. |
| `STREAM_CACHE_INVALIDATION` | `true` | Publish track changes on `stream:invalidate` so the gateway drops cached lookups. |
| `CATALOGUE_CACHE_TTL_SECONDS` | `300` | How long the gateway caches a lookup; `0` turns the cache off. |

## Backups and monitoring

| Variable | Default | Notes |
| --- | --- | --- |
| `BACKUP_ENCRYPTION_KEY` | — | Required in production (32+ characters). Every file in a backup is AES-256 encrypted with it. Keep a copy off the server: without it no backup can be restored. |
| `MONITOR_BACKUP_MAX_AGE_HOURS` | `26` | `instance:health` reports a problem when the newest backup is older. |
| `MONITOR_MIN_DISK_FREE_GB` | `10` | Free disk space threshold for `instance:health`. |

Backup schedules, retention and the offsite disk are not environment variables; set them in the admin panel under **System → Backup settings**.

## Production checklist

The deploy script stops before touching anything unless `.env` has:

- `APP_ENV=production`, `APP_DEBUG=false`
- `PUBLIC_SCHEME=https`, `TRUSTED_PROXIES=*`, `SESSION_SECURE_COOKIE=true`, `EDGE_BIND_ADDRESS=127.0.0.1`
- an `https://` `APP_URL`, with `INSTANCE_BASE_URL` and `STREAM_JWT_ISSUER` equal to it
- `DB_CONNECTION=pgsql`, `DB_HOST=postgres`, `DB_PORT=5432` and no `DB_URL`
- `REDIS_PASSWORD` and `BACKUP_ENCRYPTION_KEY` of at least 32 characters
- `infrastructure/stream-keys/private.key` and `public.key`

Check that Compose can read the file without printing secrets:

```bash
docker compose config --quiet
```

## Gateway and worker variables

Compose maps these from `.env`; you rarely set them yourself.

**stream-service:** `LISTEN_ADDR`, `CONTROL_PLANE_INTERNAL_URL`, `INTERNAL_API_KEY`, `REDIS_ADDR`, `REDIS_PASSWORD`, `MINIO_*`, `JWT_PUBLIC_KEY_PATH`, `JWT_PUBLIC_KEYS_DIRECTORY`, `JWT_LEGACY_KEY_ID`, `JWT_ISSUER`, `JWT_AUDIENCE`, `CATALOGUE_CACHE_TTL_SECONDS`. Optional tuning, with defaults:

| Variable | Default |
| --- | --- |
| `STREAM_RATE_LIMIT_PER_MINUTE` | `120` per user |
| `PRESIGN_RATE_LIMIT_PER_MINUTE` | `30` per user |
| `HLS_RATE_LIMIT_PER_MINUTE` | `600` per user |
| `PRESIGN_TTL_SECONDS` | `300` |
| `UPSTREAM_TIMEOUT_SECONDS` | `10` |
| `REQUIRE_HQ_CLAIM` | `false` |

**media-worker:** `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD`, `REDIS_QUEUE`, `MINIO_ENDPOINT`, `MINIO_ACCESS_KEY`, `MINIO_SECRET_KEY`, `MINIO_BUCKET`, `MINIO_USE_SSL`, `INTERNAL_API_KEY`.
