#!/bin/sh
set -e

# In development /app is a bind mount. Docker Desktop mounts an empty directory
# when the host path is outside its shared folders.
if [ ! -f /app/artisan ]; then
    echo "Laravel source is missing from /app. With Docker Desktop, add the repository's drive under Settings > Resources > File sharing, then recreate the container." >&2
    exit 1
fi

if [ "$APP_ENV" = "production" ] || [ "$APP_ENV" = "staging" ] || [ "$APP_ENV" = "dev" ]; then
    echo "Running production/dev optimizations..."

    # Ensure Laravel writable paths are owned by the PHP user. The signing keys
    # are mounted read-only at /app/storage/keys.
    mkdir -p /app/storage/logs /app/bootstrap/cache
    find /app/storage -path /app/storage/keys -prune -o -exec chown www-data:www-data {} +
    find /app/storage -path /app/storage/keys -prune -o -exec chmod u=rwX,g=rX,o=rX {} +
    chown -R www-data:www-data /app/bootstrap/cache
    chmod -R u=rwX,g=rX,o=rX /app/bootstrap/cache

    # Views, events, Filament assets and the storage link are built into the
    # image. Config and routes read the runtime environment. Do not run
    # filament:upgrade here: it clears the config, route and view caches.
    php artisan config:cache
    php artisan route:cache
else
    # The source is bind-mounted from the host; leave its file ownership alone.
    echo "Running in local development mode..."
    php artisan storage:link --quiet || true
fi

exec "$@"
