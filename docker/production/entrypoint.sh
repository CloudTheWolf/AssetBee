#!/usr/bin/env bash
set -euo pipefail

role="${CONTAINER_ROLE:-app}"
run_migrations="${RUN_MIGRATIONS:-false}"

echo "AssetBee: starting container (role=${role})"

# Config + routes must be cached at runtime so container env vars (especially
# APP_KEY) are applied. Livewire serves JS from /livewire-{hash}/… where the
# hash is derived from APP_KEY; a build-time route cache with a dummy key 404s.
# View/event caches and storage:link remain baked into the image.
php artisan config:cache --ansi
php artisan route:cache --ansi

wait_for_database() {
    local max_attempts="${DB_WAIT_ATTEMPTS:-30}"
    local sleep_seconds="${DB_WAIT_SLEEP:-2}"
    local attempt=1

    echo "AssetBee: waiting for database"

    while (( attempt <= max_attempts )); do
        if php artisan db:show --quiet >/dev/null 2>&1; then
            echo "AssetBee: database is ready"

            return 0
        fi

        echo "AssetBee: database not ready (attempt ${attempt}/${max_attempts})"
        sleep "${sleep_seconds}"
        attempt=$((attempt + 1))
    done

    echo "AssetBee: database did not become ready in time" >&2

    return 1
}

if [[ "${run_migrations}" == "true" && "${role}" == "app" ]]; then
    wait_for_database
    echo "AssetBee: running database migrations"
    php artisan migrate --force --ansi
fi

if [[ "$#" -gt 0 ]]; then
    exec "$@"
fi

case "${role}" in
    app)
        exec php artisan octane:frankenphp \
            --host=0.0.0.0 \
            --port=8000 \
            --admin-port=2019 \
            --log-level=INFO
        ;;
    queue)
        exec php artisan queue:work \
            --verbose \
            --tries=3 \
            --timeout=90 \
            --max-time=3600
        ;;
    scheduler)
        exec php artisan schedule:work --verbose
        ;;
    *)
        echo "Unknown CONTAINER_ROLE: ${role}" >&2
        exit 1
        ;;
esac
