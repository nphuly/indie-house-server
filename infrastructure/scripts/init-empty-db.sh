#!/bin/sh

# One-time, manual schema initialization for a verified empty homelab database.
# Never run this from GitLab CI or against a database with existing tables.
set -eu

commit_sha=${1:?commit SHA is required}
registry_image=${2:?registry image namespace is required}
case "$commit_sha" in '' | *[!0-9a-f]*) exit 2;; esac
test "${#commit_sha}" -eq 40
case "$registry_image" in '' | *[!A-Za-z0-9./:_-]*) exit 2;; esac
test "$(git rev-parse HEAD)" = "$commit_sha"
test -f .env
test -s infrastructure/stream-keys/private.key
test -s infrastructure/stream-keys/public.key
test ! -e infrastructure/deploy/current-release.env

export CONTROL_PLANE_IMAGE="$registry_image/control-plane:sha-$commit_sha"
export MEDIA_WORKER_IMAGE="$registry_image/media-worker:sha-$commit_sha"
export STREAM_SERVICE_IMAGE="$registry_image/stream-service:sha-$commit_sha"

compose() {
    docker compose --env-file .env --profile stream -f docker-compose.yml -f docker-compose.production.yml "$@"
}

compose config --quiet
compose up -d --wait --wait-timeout 120 postgres redis
tables=$(compose exec -T postgres sh -c \
    'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" -Atc "select count(*) from pg_tables where schemaname = '\''public'\'';"')
if [ "$tables" != 0 ]; then
    echo "Database already contains $tables public tables. Refusing initialization." >&2
    exit 1
fi

echo 'Initializing the verified empty database. This runs all migrations in the pinned control-plane image.'
compose run --rm --no-deps --entrypoint php control-plane artisan migrate --force
echo 'Schema initialized. Retry the manual GitLab deploy job.'
