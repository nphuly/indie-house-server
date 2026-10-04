#!/usr/bin/env bash
set -euo pipefail

# Local-only rollout for the approved additive schema change.
repo_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$repo_dir"
compose=(docker compose -f docker-compose.yml -f docker-compose.development.yml)
new_image='indie-house/control-plane:docs-todo-20260924-final'
rollback_image='indie-house/control-plane:pre-docs-todo-20260924'
backup_dir="$(dirname "$(dirname "$repo_dir")")/operational-backups"
umask 077

docker info >/dev/null
"${compose[@]}" config --quiet
docker image inspect indie-house/control-plane:latest >/dev/null
docker exec postgres pg_isready >/dev/null
if [ "$(docker exec postgres sh -c 'printf %s "$POSTGRES_DB"')" != 'indie_house' ]; then
  echo 'The running PostgreSQL database is not indie_house; stopping before any changes.' >&2
  exit 1
fi

docker image tag indie-house/control-plane:latest "$rollback_image"
CONTROL_PLANE_IMAGE="$new_image" "${compose[@]}" build control-plane

install -d -m 700 "$backup_dir"
backup_tmp="$(mktemp "$backup_dir/.indie-house-pre-docs-XXXXXXXX.dump")"
trap 'rm -f "$backup_tmp"' EXIT
docker exec postgres sh -c 'pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" -Fc' > "$backup_tmp"
test -s "$backup_tmp"
docker exec -i postgres pg_restore --list < "$backup_tmp" >/dev/null
backup_file="$backup_dir/indie-house-pre-docs-$(date -u +%Y%m%dT%H%M%SZ).dump"
mv "$backup_tmp" "$backup_file"
echo "Verified database backup: $backup_file"

for migration in \
  2026_09_21_000000_create_settings_table.php \
  2026_09_21_000100_create_backup_records_table.php \
  2026_09_24_000000_create_invites_tables.php; do
  CONTROL_PLANE_IMAGE="$new_image" "${compose[@]}" run --rm --no-deps \
    --entrypoint php control-plane artisan migrate \
    "--path=database/migrations/$migration" --force
done

docker exec postgres sh -c 'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" -Atc "select count(*) from pg_tables where schemaname = '\''public'\'' and tablename in ('\''settings'\'','\''backup_records'\'','\''invites'\'','\''invite_redemptions'\'');"' | grep -qx 4

docker image tag "$new_image" indie-house/control-plane:latest
"${compose[@]}" up -d --no-build --no-deps --force-recreate control-plane

healthy=false
for _ in $(seq 1 30); do
  if [ "$(docker inspect -f '{{.State.Health.Status}}' control_plane)" = healthy ]; then
    healthy=true
    break
  fi
  sleep 2
done
if [ "$healthy" != true ]; then
  docker image tag "$rollback_image" indie-house/control-plane:latest
  "${compose[@]}" up -d --no-build --no-deps --force-recreate control-plane
  echo 'Control plane failed health check; previous image restored. New empty tables were retained.' >&2
  exit 1
fi

"${compose[@]}" up -d --no-build --no-deps --force-recreate queue-worker scheduler
docker exec control_plane php artisan route:list --path=api/v1/mobile >/dev/null
if ! docker exec control_plane php artisan instance:health; then
  echo 'Instance health reported incomplete external configuration; the control plane is running.' >&2
fi
docker ps --format '{{.Names}} {{.Image}} {{.Status}}' \
  --filter 'name=control_plane' --filter 'name=queue_worker' --filter 'name=scheduler'
echo "Rollout complete. Rollback image: $rollback_image"
