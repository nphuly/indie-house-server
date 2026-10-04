# Operations

**English** · [Tiếng Việt](../vi/operations.md)

Day-to-day tasks for the operator of an instance. Most of them happen in the admin panel at `/admin`; the rest are Artisan commands run in the `control-plane` container.

## The admin panel

| Area | What it is for |
| --- | --- |
| **Overview** | Catalogue counts and a QR code that the apps scan to add the instance. |
| **Artists, Albums, Tracks** | The catalogue: uploads, metadata, publishing, reprocessing. |
| **Invitations** | One-off or limited invite codes with an expiry. |
| **Roles** | Permissions of the roles `super_admin`, `admin` and `user` (listener), managed with filament-shield. |
| **Mobile security** (super admins only) | Mobile sessions, streaming access, the mobile JWT audit log and JWT signing keys. |
| **System** | Data backups and backup settings. |

The admin panel never shows a token, a refresh token or a private key.

## Adding music

1. Create the **artist** (and the **album**, if any).
2. Create the **track** and upload the audio: FLAC, WAV or MP3, up to 500 MiB. A lossless source (FLAC or WAV) also gets a lossless rendition.
3. The track goes through `pending` → `processing` → `completed` (or `failed`, with the error shown on the track).
4. Turn on **Published?**. Only tracks that are both published and completed appear to listeners.

**Reprocess** (on one track or a selection) removes the existing audio renditions and sends the track back to the media worker. While it runs, the track is not public and anyone playing it is interrupted, so reprocess in batches when few people are listening.

Upload only music you have the rights to distribute to your listeners.

## Accounts and access

### Registration

`INSTANCE_REGISTRATION_MODE` decides how accounts are created:

| Mode | Behaviour |
| --- | --- |
| `closed` | No sign-up and no invitations. Only accounts created with `user:create-super-admin` exist, which suits a personal instance. |
| `invite_only` | Sign-up needs a valid invite code, on the web (`/invite/{code}`) or in the apps. Recommended for a group of friends. |
| `open` | Public sign-up. The legal pages and protections are written for an invited group; review both before opening an instance to the public. |

Invite codes are stored hashed. Each invitation can have an expiry and a maximum number of uses, and can be revoked. `INSTANCE_MAX_USERS` and `INSTANCE_MAX_ACTIVE_INVITES` cap the totals.

Give each person their own account: revoking access, rate limits, playlists and the audit log all work per user.

### Streaming access

Under **Mobile security → Streaming access**, a policy per user can:

- grant **lossless** (FLAC) playback, which is off by default;
- set the `hq` claim (only enforced when the gateway runs with `REQUIRE_HQ_CLAIM=true`);
- block streaming until a given time, with a reason.

Changes apply to the web immediately and to the apps when their access token is next issued or refreshed (within 15 minutes by default).

### Revoking devices

**Mobile security → Mobile sessions** lists each signed-in device with its platform, IP address, last use and expiry. You can revoke one session or every session of a user. Listeners can also revoke their own devices in **Settings**.

Revoking stops refreshes immediately, but an access token that was already issued keeps working until it expires (15 minutes by default), and a web playback ticket until its own expiry (60 minutes by default). To lock someone out quickly: block their streaming, revoke all their sessions, then wait one token lifetime.

The **Mobile JWT audit** log records sign-ins, refreshes, revocations, blocked attempts, refresh-token reuse and key activations, without credentials.

## Rotating signing keys

The gateway accepts several public keys, selected by `kid`, so tokens signed with the old and the new key both work during a rotation.

1. Generate the new pair and compute the public key's fingerprint:

   ```bash
   openssl genpkey -algorithm RSA -pkeyopt rsa_keygen_bits:3072 -out infrastructure/stream-keys/stream-v2.private.pem
   openssl pkey -in infrastructure/stream-keys/stream-v2.private.pem -pubout -out infrastructure/stream-public-keys/stream-v2.public.pem
   openssl pkey -pubin -in infrastructure/stream-public-keys/stream-v2.public.pem -outform DER | sha256sum
   ```

2. Restart the gateway so it loads the new public key: `docker compose up -d --force-recreate stream-service`.
3. In **Mobile security → JWT signing keys**, register `stream-v2` with that fingerprint, then activate it. The control plane checks that the private key on disk matches the fingerprint before switching.
4. After at least one access-token lifetime (and one playback-ticket lifetime), remove the old key files from both `infrastructure/stream-keys/` and `infrastructure/stream-public-keys/`. A retired key is accepted for as long as its file is mounted.

Never paste a private key into the admin panel and never mount the private key folder into the gateway. If the private key is lost, activate a new one; every app will have to sign in again.

## Artisan commands

Run them with `docker compose exec control-plane php artisan <command>`.

| Command | What it does |
| --- | --- |
| `user:create-super-admin` | Create or promote a super admin, interactively. |
| `instance:health [--json]` | Check the database, Redis, object storage, disk space, backup age and backup encryption. Exits non-zero when something fails. |
| `backup:run` | Back up the database and local storage now. |
| `backup:clean` | Apply the retention policy from the backup settings. |
| `media:purge-pending-uploads --hours=24` | Delete abandoned uploads. Scheduled daily. |
| `media:repair-uploads [--dry-run]` | Repair uploads left in temporary locations (see below). |
| `tracks:backfill-previews [--dispatch] [--limit=N]` | List tracks without a 30-second preview; with `--dispatch`, queue preview-only jobs. |
| `tracks:backfill-lossless [--dispatch] [--limit=N]` | List tracks whose original is `.flac` or `.wav` but have no FLAC rendition; with `--dispatch`, queue FLAC-only jobs. |

Backfill commands never run on deploy. Start with a small `--limit` to check the results.

### Repairing uploads

If storage refused requests for a while (for example, behind Cloudflare without the cache-bypass rule), uploads can be left in `livewire-tmp/` or `tracks/_pending/` while the database points at their final keys.

1. Fix the cause first.
2. If files have been stuck for close to 24 hours, stop the nightly cleanup: `docker compose stop scheduler`.
3. Preview, then repair:

   ```bash
   docker compose exec control-plane php artisan media:repair-uploads --dry-run
   docker compose exec control-plane php artisan media:repair-uploads
   ```

   The command moves files to the keys their records point at, re-queues tracks that never finished, moves old artist covers and profile pictures to object storage, and lists the records that need a new upload.
4. `docker compose start scheduler`.

## Monitoring

- **`instance:health --json`** is meant for a scheduler or monitoring system. It reports `ok` or the failing checks, plus estimates of database size and processed media size for trend graphs. Thresholds: `MONITOR_BACKUP_MAX_AGE_HOURS` (26) and `MONITOR_MIN_DISK_FREE_GB` (10).
- **`infrastructure/monitor/check-instance.sh`** runs on the Docker host. It checks the edge's `/up`, the gateway's `/healthz` through the edge and `instance:health`, then counts `401`, `429` and `5xx` responses in the edge log over the last five minutes. It exits `1` when a threshold is exceeded (`MONITOR_MAX_401`, `MONITOR_MAX_429`, `MONITOR_MAX_5XX`); connect that exit code to your alerting.
- **Gateway access log.** Each audio request logs one JSON line with the status, bytes, timings per stage (`limit_ms`, `resolve_ms`, `stat_ms`, `get_ms`, `first_byte_ms`), the requested and served quality, the cache result and a failure reason. It contains no token, object key, presigned URL or IP address.

  ```bash
  docker compose logs --no-log-prefix --since 24h stream-service | grep -E '"level":"(WARN|ERROR)"'
  docker compose logs --no-log-prefix --since 24h stream-service | grep -o '"failure":"[^"]*"' | sort | uniq -c | sort -rn
  ```

- **Edge log.** Caddy logs status and duration only; the whole request object is dropped so invite URLs, tokens and cookies are never written.

Container logs rotate at 5 × 10 MB per service.

## Redis

Redis holds the queue and web sessions and runs with AOF persistence, so a power cut does not lose them. Never start a Redis that has only RDB files with `--appendonly yes`: it would start empty. The deploy script switches a running server to AOF first.

With a password set, `redis-cli` in the container authenticates only through `REDISCLI_AUTH`:

```bash
docker compose exec redis sh -c 'REDISCLI_AUTH="$REDIS_PASSWORD" redis-cli ping'
```

## Housekeeping

- The deploy script keeps a verified database dump in `infrastructure/deploy/backups/` before each migration. It does not delete old dumps: move them to safe storage and clean up on your own schedule.
- Keep the backup encryption key, `.env` and the signing keys outside the server too. See [Backup and restore](backup-and-restore.md).
- Run the dependency audits from [Security](security.md#regular-checks) from time to time.
