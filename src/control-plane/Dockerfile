FROM composer:2 AS composer

FROM dunglas/frankenphp:1.12.7-php8.4-alpine AS base

RUN install-php-extensions \
        pdo_pgsql \
        pgsql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        intl \
        zip \
        opcache \
        redis \
    && apk add --no-cache curl postgresql-client

WORKDIR /app

COPY docker/php/uploads.ini $PHP_INI_DIR/conf.d/uploads.ini

# =========================================================================
# STAGE 2: Composer Dependencies Builder
# =========================================================================
FROM base AS composer-builder
COPY --from=composer /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock ./
# Trim the AWS SDK to the services listed in composer.json here, so the unused
# ones never reach an image layer.
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction \
    && composer run-script pre-autoload-dump --no-dev --no-interaction

# =========================================================================
# STAGE 3: Node.js Asset Builder (For Vite & Filament CSS/JS)
# =========================================================================
FROM node:22-alpine AS frontend-builder
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts
COPY . .
COPY --from=composer-builder /app/vendor /app/vendor
RUN npm run build

# =========================================================================
# STAGE 4: Local & Dev Development Target
# =========================================================================
FROM base AS development

RUN install-php-extensions xdebug

COPY --from=composer /usr/bin/composer /usr/bin/composer

# The source is bind-mounted at runtime. These packages seed the shared vendor
# volume the first time it is created; afterwards run Composer in the container.
COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-scripts --prefer-dist

COPY --chmod=755 docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]

# =========================================================================
# STAGE 5: Test suite (CI builds this target to run the tests; not pushed)
# =========================================================================
FROM development AS test
ENV XDEBUG_MODE=off
COPY . .
# Views render @vite, so the tests need the same manifest as production.
COPY --from=frontend-builder /app/public/build /app/public/build
RUN mkdir -p storage/app/private storage/app/public storage/framework/cache/data \
        storage/framework/sessions storage/framework/testing storage/framework/views \
        storage/logs bootstrap/cache \
    && composer test

# =========================================================================
# STAGE 6: Production Target (default: keep it the last stage)
# =========================================================================
FROM base AS production

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php/opcache.ini $PHP_INI_DIR/conf.d/opcache.ini

COPY --from=composer-builder /app/vendor /app/vendor
COPY . .
COPY --from=frontend-builder /app/public/build /app/public/build

# Composer is mounted for this step only, so the image does not ship it. The
# autoload scripts publish Filament assets. Views, events and the storage link
# do not depend on the runtime environment, so they are built here once.
RUN --mount=type=bind,from=composer,source=/usr/bin/composer,target=/usr/bin/composer \
    mkdir -p storage/app/private storage/app/public storage/framework/cache/data \
        storage/framework/sessions storage/framework/testing storage/framework/views \
        storage/logs bootstrap/cache \
    && composer dump-autoload --optimize --no-dev --no-interaction \
    && php artisan view:cache \
    && php artisan event:cache \
    && php artisan storage:link \
    && chown -R www-data:www-data storage bootstrap/cache

COPY --chmod=755 docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]

