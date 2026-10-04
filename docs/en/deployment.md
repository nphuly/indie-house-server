# Deployment

**English** · [Tiếng Việt](../vi/deployment.md)

This guide sets up a production instance on one Linux server with Docker. The reference deployment runs on a VM in a homelab, with Cloudflare Tunnel providing HTTPS, so no port is opened on the router.

```text
Browser / app ──HTTPS──▶ Cloudflare ──tunnel──▶ cloudflared on the server
                                                  ├─ example.com          → 127.0.0.1:8080 → Caddy edge (Docker)
                                                  └─ objects.example.com  → <storage>:9000 → MinIO S3 API
```

Before you start, read [DISCLAIMER.md](../../DISCLAIMER.md): you are responsible for the server, the data and the rights to every track you upload.

## Requirements

- A Linux server or VM with Docker Engine, the Compose plugin, Git, curl and OpenSSL. Transcoding is the heaviest task; Compose limits the media worker and the queue worker to 2 CPUs and 2 GB of memory each.
- A stable HTTPS hostname. Its origin is the instance's identity: apps and tokens are tied to it.
- S3-compatible object storage with a private bucket and credentials limited to that bucket.
- A way to bring HTTPS to the server. This guide uses Cloudflare Tunnel; see [Other HTTPS proxies](#other-https-proxies).

## 1. Prepare the server

Create a dedicated account for deployments and give only that account access to Docker (Docker access is equivalent to root):

```bash
sudo adduser --disabled-password --gecos '' indie-house
sudo usermod -aG docker indie-house
sudo install -d -o indie-house -g indie-house -m 0750 /srv/indie-house
sudo -u indie-house git clone https://github.com/nphuly/indie-house-server.git /srv/indie-house
```

Production Compose publishes no database or Redis port. The only published port is the edge, on `127.0.0.1:8080`.

## 2. Configure

As the `indie-house` user, in `/srv/indie-house`:

```bash
test ! -e .env && cp .env.example .env
chmod 600 .env
```

Fill in `.env` following [Configuration](configuration.md) and its [production checklist](configuration.md#production-checklist). Add these two lines so that plain `docker compose` commands use the production file and the gateway profile:

```dotenv
COMPOSE_FILE=docker-compose.yml:docker-compose.production.yml
COMPOSE_PROFILES=stream
```

Create the signing keys once (skip this if the instance already has keys):

```bash
install -d -m 0700 infrastructure/stream-keys
(umask 077; openssl genpkey -algorithm RSA -pkeyopt rsa_keygen_bits:3072 -out infrastructure/stream-keys/private.key)
openssl pkey -in infrastructure/stream-keys/private.key -pubout -out infrastructure/stream-keys/public.key
```

Back up `.env` and the keys somewhere safe before going further.

## 3. Object storage

- Keep the bucket private. Indie House never needs public objects.
- Give the application its own access key limited to the bucket.
- Expose only the S3 API, never the storage console.
- Point `MINIO_ENDPOINT` (Go services) and `AWS_INTERNAL_ENDPOINT` (Laravel) at the storage's local address, and `AWS_ENDPOINT` / `MINIO_PUBLIC_ENDPOINT` at the public hostname. Clients only use the public hostname for signed uploads, artwork and presigned URLs; audio for the web goes through the gateway.

## 4. HTTPS with Cloudflare Tunnel

1. Add your domain to Cloudflare and wait until the zone is active.
2. In **Networking → Tunnels**, create a tunnel, install the `cloudflared` service on the server with the command the dashboard shows, and wait until the connector is healthy. Treat the tunnel token as a secret.
3. Add two **published application** routes, without a path:

   | Hostname | Service | HTTP Host header |
   | --- | --- | --- |
   | `example.com` | `http://127.0.0.1:8080` | `example.com` |
   | `objects.example.com` | `http://<storage-address>:9000` | `objects.example.com` |

   Storage must see the same hostname that requests were signed for.
4. Create a Cache Rule that **bypasses the cache** for both hostnames. This is required for the storage hostname: without it, signed `HEAD` requests on new `.flac` and `.jpg` objects fail with `403`, and uploads get stuck. Check it with `curl -sI https://objects.example.com/<bucket>/x.jpg | grep -i cf-cache-status`, which must print `DYNAMIC`.
5. Do not put Cloudflare Access in front of either hostname: the apps and presigned URLs cannot sign in through it.

Cloudflare's Free and Pro plans limit a request body to 100 MB, so larger uploads through the tunnel fail with `413`.

### Other HTTPS proxies

Any proxy on the same host that terminates TLS and forwards to `http://127.0.0.1:8080` works. The edge reads the visitor's address from `Cf-Connecting-Ip`; with another proxy, change `client_ip_headers` in `infrastructure/caddy/Caddyfile` to the header your proxy sets (for example `X-Forwarded-For`), and keep the edge on loopback.

## 5. Build and start

### Manual: build on the server

```bash
docker build --target production -t indie-house/control-plane:current src/control-plane
docker build -t indie-house/media-worker:current src/media-worker
docker build -t indie-house/stream-service:current src/stream-service

docker compose up -d postgres redis
docker compose run --rm --no-deps --entrypoint php control-plane artisan migrate --force
docker compose up -d
```

The production Compose file uses the `indie-house/<service>:current` tags by default.

### Automated: release pipeline and deploy script

The repository includes the pipeline used by the reference deployment (`.gitlab-ci.yml`, written for GitLab CI). It runs only on protected `release/X.Y[.Z]` branches:

1. **Validate:** `php -l` on every PHP file, `docker compose config` for both environments, `go vet` and `go test` for both Go modules, `sh -n` on the deploy scripts plus their test script, and the Laravel suite built from the Dockerfile `test` target.
2. **Build:** the three images with rootless BuildKit, in parallel with validation, tagged `sha-<commit>`, with a registry build cache.
3. **Deploy:** a manual job that SSHes to the server and runs `infrastructure/scripts/deploy-release.sh` with the commit SHA.

The deploy script, on the server:

- refuses to run unless `.env` passes the [production checklist](configuration.md#production-checklist) and the keys exist;
- checks out the exact commit and pulls the three images for it, using read-only registry credentials kept in `/etc/indie-house/registry.env`;
- switches an existing Redis to AOF persistence safely if needed;
- if migrations are pending, writes and verifies a PostgreSQL dump, then runs `migrate --force` from the new image **before** replacing any application container;
- starts the new release, reloads the edge (`caddy reload`), and waits for `/up` and `/.well-known/indie-house` to answer `200`;
- if the rollout fails, puts the previous images back. It never reverses migrations, never seeds, never reprocesses media and never removes volumes;
- after a healthy rollout, tags the images `:current` and removes release images older than the previous one.

To use another CI system, call the same script with `<commit-sha> <registry-image> <release-branch>` from your own deploy job.

## 6. Create the first admin

```bash
docker compose exec control-plane php artisan user:create-super-admin
```

The command asks for the name, email and password. Never pass a password as a command-line argument. Then sign in at `https://example.com/admin`, set `INSTANCE_REGISTRATION_MODE` to how you want people to join, and create invitations under **Invitations**.

## 7. Check the deployment

```bash
curl -s -o /dev/null -w '%{http_code}\n' https://example.com/up                          # 200, no redirect
curl -s https://example.com/.well-known/indie-house                                       # 200, URLs on https://example.com
curl -s -o /dev/null -w '%{http_code}\n' https://example.com/api/internal/tracks/status   # 404
curl -sI https://example.com/ | grep -i -E 'content-security|strict-transport'            # both headers
```

Then upload a short track in the admin panel, wait for it to reach **completed**, publish it, and play it from the web and from a phone on mobile data.

## Upgrading

With the pipeline, push the reviewed commit to a release branch and run the deploy job. Manually:

```bash
git pull
# rebuild the three images as in step 5
docker compose run --rm --no-deps --entrypoint php control-plane artisan migrate --force
docker compose up -d
docker compose exec edge caddy reload --config /etc/caddy/Caddyfile --adapter caddyfile
```

Back up the database before migrating. A changed Caddyfile does not recreate the edge container, so always reload it. Migrations are written to work with the release that is still running, so a failed rollout can go back to the previous images without touching the schema.

## Troubleshooting

| Symptom | Check |
| --- | --- |
| Cloudflare `1033` | `cloudflared` is not connected. Check `sudo systemctl status cloudflared` and the tunnel status. |
| Cloudflare `502` | The tunnel is up but the origin is not: `curl http://127.0.0.1:8080/up` on the server. |
| Cloudflare `522` | The DNS record for the hostname is not the tunnel's `cfargotunnel.com` CNAME (often an imported A record). |
| `/up` redirects to itself (`308`) | The control plane needs `SERVER_NAME=:80` (set in Compose) and `PUBLIC_SCHEME=https`. |
| Manifest `503`, or sign-in works but audio returns `401` | `APP_URL`, `INSTANCE_BASE_URL` and `STREAM_JWT_ISSUER` differ, `INSTANCE_ID` is not a UUID, or containers were not recreated after editing `.env`. |
| Uploads stuck, artwork missing after saving a form | The storage hostname has no cache-bypass rule. Add it, then run `php artisan media:repair-uploads --dry-run` (see [Operations](operations.md#repairing-uploads)). |
| `413` on upload | The file is larger than the proxy's request limit. |
