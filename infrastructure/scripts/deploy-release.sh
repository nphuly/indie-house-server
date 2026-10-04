#!/bin/sh

# Runs on the deployment VPS. Pending migrations run from the pinned release
# image after a verified database dump, before application containers change.
set -eu

commit_sha=${1:?commit SHA is required}
registry_image=${2:?registry image namespace is required}
release_branch=${3:?release branch is required}
registry_credentials_file=${HOMELAB_REGISTRY_CREDENTIALS_FILE:-/etc/indie-house/registry.env}

case "$commit_sha" in
    '' | *[!0-9a-f]*)
        echo "Expected a 40-character lowercase commit SHA." >&2
        exit 2
        ;;
esac
if [ "${#commit_sha}" -ne 40 ]; then
    echo "Expected a 40-character lowercase commit SHA." >&2
    exit 2
fi

case "$registry_image" in
    '' | *[!A-Za-z0-9./:_-]*)
        echo "Registry image namespace contains unsupported characters." >&2
        exit 2
        ;;
esac
printf '%s\n' "$release_branch" | grep -Eq '^release/[0-9]+\.[0-9]+(\.[0-9]+)?$'

test -f .env
test -f "$registry_credentials_file"
test -d .git
test -s infrastructure/stream-keys/private.key
test -s infrastructure/stream-keys/public.key

# Refuse a release that would expose production sessions or URLs over HTTP.
# These exact values are documented for the loopback-only HTTPS-proxy setup.
require_setting() {
    if ! grep -Fqx "$1=$2" .env; then
        echo "Production .env must set $1=$2." >&2
        exit 1
    fi
}
require_setting APP_ENV production
require_setting APP_DEBUG false
require_setting PUBLIC_SCHEME https
require_setting TRUSTED_PROXIES '*'
require_setting SESSION_SECURE_COOKIE true
require_setting EDGE_BIND_ADDRESS 127.0.0.1
public_url=$(sed -n 's/^APP_URL=//p' .env | tail -n 1)
case "$public_url" in https://* ) ;; * ) echo 'APP_URL must be HTTPS.' >&2; exit 1;; esac
require_setting INSTANCE_BASE_URL "$public_url"
require_setting STREAM_JWT_ISSUER "$public_url"
require_setting DB_CONNECTION pgsql
require_setting DB_HOST postgres
require_setting DB_PORT 5432
# Redis holds web sessions and the media queue; production must require a
# password (docker-compose.yml turns on requirepass when it is set).
if ! grep -Eq '^REDIS_PASSWORD=.{32,}$' .env; then
    echo 'Production .env must set REDIS_PASSWORD to at least 32 characters (openssl rand -hex 32).' >&2
    exit 1
fi
# Backups hold every account and are copied offsite, so they are encrypted;
# without a key every scheduled backup would fail.
if ! grep -Eq '^BACKUP_ENCRYPTION_KEY=.{32,}$' .env; then
    echo 'Production .env must set BACKUP_ENCRYPTION_KEY to at least 32 characters (openssl rand -base64 32).' >&2
    exit 1
fi
if grep -Eq '^DB_URL=.' .env; then
    echo 'DB_URL must be unset so the application and backup target the same PostgreSQL service.' >&2
    exit 1
fi

state_file=infrastructure/deploy/current-release.env
previous_checkout=$(git rev-parse HEAD)
switched=false
migration_started=false
docker_config=$(mktemp -d)
status_file=$(mktemp)
state_tmp=
backup_tmp=

compose() {
    # A rollback can check out a release from before this file was renamed.
    production_file=docker-compose.production.yml
    if [ ! -f "$production_file" ]; then production_file=docker-compose.overlay.yml; fi
    docker compose --env-file .env --profile stream -f docker-compose.yml -f "$production_file" "$@"
}

# The edge reads a bind-mounted Caddyfile, which `up` does not compare, so a
# release that changes routes needs a reload. If the new file is invalid,
# Caddy keeps the running config and the release fails here.
reload_edge() {
    compose exec -T edge caddy reload --config /etc/caddy/Caddyfile --adapter caddyfile
}

# redis-cli inside the Redis container, authenticated when that container has
# a password. A server from before REDIS_PASSWORD was set needs none.
redis_cli() {
    compose exec -T redis sh -c '[ -z "$REDIS_PASSWORD" ] || export REDISCLI_AUTH="$REDIS_PASSWORD"; exec redis-cli "$@"' redis-cli "$@"
}

finish() {
    result=$?
    trap - EXIT
    if [ "$result" -ne 0 ]; then
        if [ "$switched" = true ] && [ -s "$state_file" ]; then
            # A failed application rollout can be reverted without touching
            # named volumes, database rows, or media objects.
            # shellcheck disable=SC1090
            . "$state_file"
            git checkout --detach --quiet "$DEPLOYED_SHA" || true
            export CONTROL_PLANE_IMAGE="$DEPLOYED_REGISTRY_IMAGE/control-plane:sha-$DEPLOYED_SHA"
            export MEDIA_WORKER_IMAGE="$DEPLOYED_REGISTRY_IMAGE/media-worker:sha-$DEPLOYED_SHA"
            export STREAM_SERVICE_IMAGE="$DEPLOYED_REGISTRY_IMAGE/stream-service:sha-$DEPLOYED_SHA"
            if ! compose up -d --no-build; then
                echo 'Automatic rollback failed; inspect the running Compose services.' >&2
            else
                reload_edge || echo 'Could not reload the edge with the previous Caddyfile.' >&2
                echo "Restored previous release $DEPLOYED_SHA." >&2
            fi
        else
            git checkout --detach --quiet "$previous_checkout" || true
            if [ "$switched" = true ]; then
                echo 'First deployment failed; there is no previous release to restore.' >&2
            fi
        fi
        if [ "$migration_started" = true ]; then
            echo 'Database migrations may have changed the schema. Application rollback does not reverse them; inspect the migration log and backup before retrying.' >&2
        fi
    fi
    rm -rf "$docker_config"
    rm -f "$status_file"
    if [ -n "$state_tmp" ]; then rm -f "$state_tmp"; fi
    if [ -n "$backup_tmp" ]; then rm -f "$backup_tmp"; fi
    exit "$result"
}
trap finish EXIT
trap 'exit 130' HUP INT TERM
export DOCKER_CONFIG="$docker_config"

# Do not overwrite a local operator's tracked work. Ignored .env and key files
# stay in place because this script never calls git clean.
git diff --quiet
git diff --cached --quiet
git fetch --quiet origin "refs/heads/$release_branch"
if [ "$(git rev-parse FETCH_HEAD)" != "$commit_sha" ]; then
    echo 'Release branch advanced after this pipeline; deploy a new pipeline instead.' >&2
    exit 1
fi
git checkout --detach --quiet "$commit_sha"

# Registry credentials stay on the VPS. An isolated Docker config means this
# deploy does not alter a human operator's Docker login.
# shellcheck disable=SC1090
. "$registry_credentials_file"
: "${REGISTRY_HOST:?REGISTRY_HOST is required}"
: "${REGISTRY_IMAGE:?REGISTRY_IMAGE is required}"
: "${REGISTRY_USERNAME:?REGISTRY_USERNAME is required}"
: "${REGISTRY_PASSWORD:?REGISTRY_PASSWORD is required}"
if [ "$registry_image" != "$REGISTRY_IMAGE" ]; then
    echo 'CI image namespace differs from the trusted VM Registry configuration.' >&2
    exit 1
fi
case "$REGISTRY_IMAGE" in "$REGISTRY_HOST"/*) ;; *) echo 'Registry image host mismatch.' >&2; exit 1;; esac

printf '%s' "$REGISTRY_PASSWORD" | docker login "$REGISTRY_HOST" --username "$REGISTRY_USERNAME" --password-stdin

export CONTROL_PLANE_IMAGE="$registry_image/control-plane:sha-$commit_sha"
export MEDIA_WORKER_IMAGE="$registry_image/media-worker:sha-$commit_sha"
export STREAM_SERVICE_IMAGE="$registry_image/stream-service:sha-$commit_sha"

compose config --quiet
compose pull control-plane media-worker stream-service
for image in "$CONTROL_PLANE_IMAGE" "$MEDIA_WORKER_IMAGE" "$STREAM_SERVICE_IMAGE"; do
    revision=$(docker image inspect --format '{{ index .Config.Labels "org.opencontainers.image.revision" }}' "$image")
    if [ "$revision" != "$commit_sha" ]; then
        echo "Pulled image has the wrong source revision: $image" >&2
        exit 1
    fi
done

# Redis loads an empty dataset when AOF is first enabled from its command line.
# Switch a running RDB-only server live, so the recreated container starts from
# AOF files that already hold the queued jobs and web sessions.
if [ -n "$(compose ps -q redis)" ]; then
    redis_aof=$(redis_cli CONFIG GET appendonly)
    redis_aof=$(printf '%s\n' "$redis_aof" | tr -d '\r' | sed -n 2p)
    case "$redis_aof" in
        yes) ;;
        no)
            redis_cli CONFIG SET appendonly yes > /dev/null
            aof_ready=false
            for attempt in 1 2 3 4 5 6 7 8 9 10 11 12 13 14 15 16 17 18 19 20; do
                persistence=$(redis_cli INFO persistence | tr -d '\r')
                if printf '%s\n' "$persistence" | grep -qx 'aof_rewrite_in_progress:0' \
                    && printf '%s\n' "$persistence" | grep -qx 'aof_rewrite_scheduled:0' \
                    && printf '%s\n' "$persistence" | grep -qx 'aof_last_bgrewrite_status:ok'; then
                    aof_ready=true
                    break
                fi
                sleep 3
            done
            if [ "$aof_ready" != true ]; then
                echo 'Redis did not finish writing its first AOF; refusing to recreate it.' >&2
                exit 1
            fi
            echo 'Enabled Redis AOF on the running server.'
            ;;
        *)
            echo 'Could not read the Redis appendonly setting.' >&2
            exit 1
            ;;
    esac
fi
compose up -d --wait --wait-timeout 120 postgres redis

# An empty public schema is the only case where Laravel has no migrations
# repository yet. A populated schema with an unreadable repository is an error.
tables=$(compose exec -T postgres sh -c \
    'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" -Atc "select count(*) from pg_tables where schemaname = '\''public'\'';"')
case "$tables" in '' | *[!0-9]*) echo 'Could not verify the public schema table count.' >&2; exit 1;; esac

migration_pending=false
if [ "$tables" -eq 0 ]; then
    if [ -s "$state_file" ]; then
        echo 'Previously deployed release has an empty database; refusing to initialize it.' >&2
        exit 1
    fi
    migration_pending=true
else
    if ! compose run --rm --no-deps --entrypoint php control-plane artisan migrate:status --no-ansi > "$status_file"; then
        echo 'Database migration status is unavailable; refusing to change the schema.' >&2
        exit 1
    fi
    if grep -q 'Pending' "$status_file"; then migration_pending=true; fi
fi

if [ "$migration_pending" = true ]; then
    if [ "$tables" -gt 0 ]; then
        # Keep this outside Git and fail closed if the dump cannot be created or
        # read back. pg_dump produces a consistent database snapshot.
        backup_dir=infrastructure/deploy/backups
        umask 077
        mkdir -p "$backup_dir"
        chmod 700 "$backup_dir"
        backup_tmp=$(mktemp "$backup_dir/.pre-migrate-${commit_sha}-$(date -u +%Y%m%dT%H%M%SZ)-XXXXXXXX")
        if ! compose exec -T postgres sh -c 'pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" -Fc' > "$backup_tmp" \
            || [ ! -s "$backup_tmp" ] \
            || ! compose exec -T postgres pg_restore --list < "$backup_tmp" > /dev/null; then
            echo 'Database backup failed verification; migrations were not started.' >&2
            exit 1
        fi
        backup_name=${backup_tmp##*/}
        backup_file="$backup_dir/${backup_name#.}.dump"
        mv "$backup_tmp" "$backup_file"
        backup_tmp=
        echo "Verified database backup: $backup_file"
        cat "$status_file"
    else
        echo 'Empty public schema verified; initializing database from the pinned release image.'
    fi

    migration_started=true
    compose run --rm --no-deps --entrypoint php control-plane artisan migrate --force --no-interaction --no-ansi
    if ! compose run --rm --no-deps --entrypoint php control-plane artisan migrate:status --no-ansi > "$status_file" \
        || grep -q 'Pending' "$status_file"; then
        echo 'Migration verification failed; application containers were not updated.' >&2
        exit 1
    fi
fi

switched=true
compose up -d --no-build
reload_edge

edge_binding=$(compose port edge 80)
case "$edge_binding" in
    '' | 0.0.0.0:* | '[::]:'*)
        echo 'Edge must bind a specific loopback or private address.' >&2
        exit 1
        ;;
esac
edge_url="http://$edge_binding"
has_http_200() {
    status=$(curl --silent --output /dev/null --write-out '%{http_code}' --max-time 5 "$1") || return 1
    [ "$status" = 200 ]
}
healthy=false
for attempt in 1 2 3 4 5 6 7 8 9 10 11 12; do
    if has_http_200 "$edge_url/up" \
        && has_http_200 "$edge_url/.well-known/indie-house"; then
        healthy=true
        break
    fi
    sleep 5
done
if [ "$healthy" != true ]; then
    echo 'Release health check failed.' >&2
    exit 1
fi
for service in control-plane queue-worker scheduler media-worker stream-service edge; do
    container_id=$(compose ps -q "$service")
    if [ -z "$container_id" ] || [ "$(docker inspect -f '{{.State.Running}}' "$container_id")" != true ]; then
        echo "Release service $service is not running." >&2
        exit 1
    fi
done

previous_release=
if [ -s "$state_file" ]; then
    previous_release=$(sed -n 's/^DEPLOYED_SHA=//p' "$state_file" | tail -n 1)
fi
umask 077
state_tmp=$(mktemp "${state_file}.XXXXXX")
printf 'DEPLOYED_SHA=%s\nDEPLOYED_REGISTRY_IMAGE=%s\n' "$commit_sha" "$registry_image" > "$state_tmp"
mv "$state_tmp" "$state_file"
state_tmp=
echo "Deployed $commit_sha; database migrations are current."

# The release is live from here on, so housekeeping failures only warn. The
# ":current" tags are the image defaults in docker-compose.production.yml.
for component in control-plane media-worker stream-service; do
    docker tag "$registry_image/$component:sha-$commit_sha" "indie-house/$component:current" \
        || echo "Could not tag indie-house/$component:current." >&2
done
# Keep this release and the one it replaced for a manual rollback; older
# release images only use disk space.
docker image ls --format '{{.Repository}}:{{.Tag}}' --filter "reference=$registry_image/*:sha-*" \
    | while read -r image; do
        case "$image" in
            *":sha-$commit_sha" | *":sha-${previous_release:-none}") ;;
            *) docker image rm "$image" > /dev/null 2>&1 || echo "Kept $image; it is still in use." >&2 ;;
        esac
    done || echo 'Could not prune old release images.' >&2
