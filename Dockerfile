# syntax=docker/dockerfile:1

# -----------------------------------------------------------------------------
# PHP dependencies (no scripts — discovery runs in the runtime image)
# -----------------------------------------------------------------------------
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --no-interaction \
    --ignore-platform-reqs

COPY . .

RUN composer dump-autoload --optimize --no-scripts

# -----------------------------------------------------------------------------
# Frontend assets
# -----------------------------------------------------------------------------
FROM oven/bun:1 AS frontend

WORKDIR /app

COPY package.json bun.lock ./
COPY vite.config.js ./
COPY resources ./resources

# Vite / Flux resolve assets from Composer packages
COPY --from=vendor /app/vendor/livewire /app/vendor/livewire
COPY --from=vendor /app/vendor/laravel/framework/src/Illuminate/Pagination/resources \
    /app/vendor/laravel/framework/src/Illuminate/Pagination/resources

RUN bun install --frozen-lockfile || bun install

RUN bun run build

# -----------------------------------------------------------------------------
# Production runtime (FrankenPHP + Laravel Octane)
# -----------------------------------------------------------------------------
FROM dunglas/frankenphp:1-php8.4-bookworm AS production

WORKDIR /app

RUN install-php-extensions \
        bcmath \
        exif \
        gd \
        intl \
        opcache \
        pcntl \
        pdo_mysql \
        redis \
        zip \
    && useradd --create-home --shell /bin/bash appuser \
    && setcap -r /usr/local/bin/frankenphp \
    && mkdir -p /data /config \
    && chown -R appuser:appuser /data /config

COPY docker/production/php.ini /usr/local/etc/php/conf.d/99-assetbee.ini
COPY docker/production/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint.sh \
    && chmod +x /usr/local/bin/entrypoint.sh

COPY --from=vendor --chown=appuser:appuser /app /app

RUN find vendor -name tests -o -name test -o -name fixtures | xargs rm -rf && \
    find vendor -name '*.md' ! -name 'README.md' | xargs rm -rf
COPY --from=frontend --chown=appuser:appuser /app/public/build /app/public/build

RUN mkdir -p \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/framework/testing \
        storage/logs \
        storage/app/public \
        bootstrap/cache \
    && chown -R appuser:appuser storage bootstrap/cache \
    && APP_ENV=production APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= \
        php artisan package:discover --ansi \
    && APP_ENV=production APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= \
        php artisan event:cache --ansi \
    && APP_ENV=production APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= \
        php artisan view:cache --ansi \
    && APP_ENV=production APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= \
        php artisan storage:link --force --ansi \
    && chown -R appuser:appuser storage bootstrap/cache public/storage

# Route cache is built at container start (see entrypoint). Livewire endpoint
# paths are derived from APP_KEY, so baking route:cache here with a dummy key
# makes /livewire-*/livewire.min.js 404 in production.

USER appuser

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    OCTANE_SERVER=frankenphp \
    XDG_CONFIG_HOME=/config \
    XDG_DATA_HOME=/data \
    SERVER_NAME=:8000

EXPOSE 8000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
