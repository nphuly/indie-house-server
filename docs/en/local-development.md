# Local development

**English** · [Tiếng Việt](../vi/local-development.md)

The development stack runs the PHP services in Docker with the source bind-mounted, so PHP changes apply without a rebuild. Vite runs on your machine.

## Requirements

- Docker with the Compose plugin
- Node.js 22 and npm (for Vite)
- OpenSSL (to generate secrets and keys)
- Go 1.27 if you work on the Go services outside Docker
- An S3-compatible bucket (for example a local MinIO) once you want to upload tracks. The demo catalogue works without one.

## First run

```bash
cp .env.example .env
```

Edit `.env`:

| Variable | Development value |
| --- | --- |
| `APP_KEY` | Output of `printf 'base64:%s\n' "$(openssl rand -base64 32)"` |
| `APP_URL` | `http://localhost` (the development control plane listens on port 80) |
| `DB_USERNAME`, `DB_PASSWORD` | Any values; Compose creates the database with them |
| `REDIS_PASSWORD` | Leave empty |
| `AWS_*`, `MINIO_*` | Your bucket, when you have one (see [Configuration](configuration.md#object-storage)) |

Generate a signing key pair. The control plane needs it to issue mobile tokens, and the gateway mounts the public half:

```bash
openssl genpkey -algorithm RSA -pkeyopt rsa_keygen_bits:3072 -out infrastructure/stream-keys/private.key
openssl pkey -in infrastructure/stream-keys/private.key -pubout -out infrastructure/stream-keys/public.key
```

Both folders under `infrastructure/` are ignored by Git. Never commit key material, not even development keys.

Build and start:

```bash
make build
make start              # control-plane on http://localhost, PostgreSQL and Redis on 127.0.0.1
make composer-install   # installs vendor/ into the shared volume
make migrate
make create-super-admin # asks for name, email and password
```

Load the demo catalogue (two artists, two albums and six generated preview tones, plus a `test@example.com` / `password` listener):

```bash
docker compose --project-name indie-house -f docker-compose.yml -f docker-compose.development.yml \
  exec control-plane php artisan db:seed
```

Then start Vite on the host:

```bash
cd src/control-plane
npm ci
npm run dev
```

Open <http://localhost> for the listener app and <http://localhost/admin> for the admin panel. Search for "Nox", "Mây" or "Ambient" to find the demo tracks.

## What runs where

| Service | Development behaviour |
| --- | --- |
| `control-plane`, `queue-worker`, `scheduler` | `development` image target with Xdebug, tagged `indie-house/control-plane:dev`. `src/control-plane` is mounted at `/app`; `vendor/` lives in the shared `control_plane_vendor` volume, so run Composer inside the container. |
| `media-worker` | Built from `src/media-worker`. Needs object storage to do anything. |
| `stream-service` | In the Compose profile `stream`, so `make start` leaves it out. Add `--profile stream` to run it; it listens on port `8081`. |
| `postgres`, `redis` | Published on `127.0.0.1` only. Redis has no password in development. |

There is no Caddy edge in development, so nothing routes `/api/v1/stream/*`. The development Compose file sets `STREAM_WEB_AUDIO_VIA_GATEWAY=false`, which makes Laravel stream web audio itself. Demo tracks are always served by Laravel because they live on the local `public` disk.

The iOS and Android apps only connect to HTTPS instances. To try them against your machine, put an HTTPS tunnel in front of a production-like stack, or use a staging instance.

## Useful commands

```bash
make help                        # list Makefile targets
make logs SERVICE=control-plane  # follow one service
make restart
make rebuild                     # rebuild images without cache
```

Project Artisan commands are listed in [Operations](operations.md#artisan-commands).

### Debugging

The development image includes Xdebug in `debug` mode for the web container, connecting to `host.docker.internal` with the server name `docker`. Set `XDEBUG_MODE: debug` on `queue-worker` or `scheduler` in `docker-compose.development.yml` to step through jobs or scheduled commands.

## Tests

```bash
make test                               # Laravel suite in the development container
cd src/stream-service && go test ./...
cd src/media-worker && go test ./...
sh infrastructure/scripts/test-deploy-release.sh
```

- The Laravel suite runs on in-memory SQLite, so host PHP needs `pdo_sqlite` if you run `composer test` outside Docker.
- The views under test render `@vite`, so run `npm run build` once to create `public/build/manifest.json`.
- Run a single test with `php artisan test --filter=NameOfTest`.
- Format PHP with `vendor/bin/pint` and Go with `gofmt`.

## Conventions

- **Public catalogue.** Any query a listener can reach uses `Track::scopePublicCatalogue()`, including eager loads.
- **Public IDs.** Routes bind on the 12-character `public_id`; never expose database keys.
- **PostgreSQL-only code.** Search uses `unaccent` and `pg_trgm`. Code that depends on them checks the driver and falls back, so the SQLite test suite still runs; tests of Postgres-only behaviour are skipped on other drivers.
- **Content-Security-Policy.** Listener, auth and `/legacy` pages run only same-origin scripts carrying the request nonce: no inline `<script>` and no `on*=` attributes (JSON in `<script type="application/json">` is fine). A new external origin must be added to `SecurityHeaders`.
- **Translations.** `lang/vi` and `lang/en` mirror each other file for file and key for key; `LocaleTest` checks the listener groups. Strings used by `app.js` live in `lang/*/listen_client.php`.
- **Settings.** Runtime settings live in the database. Read them with `Settings::get($group, $key)` and call `Settings::forget($group)` after writing.
- **Uploads.** Create pages use `PromotesPendingUploads`; never move an upload with an unchecked `Storage::move()`.
- **Cross-language contracts.** The media job, the internal callbacks and the `stream:invalidate` channel are shared with Go. Change both sides in the same change.
- **Migrations.** They must work with the release that is already running, because deploys migrate before swapping containers and never roll migrations back.
- **Legal pages.** If you change what is stored or logged, or add a third party, update `lang/{vi,en}/legal.php` and [Privacy](privacy.md) in the same change.
