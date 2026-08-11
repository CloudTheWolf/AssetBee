#!/usr/bin/env bash
set -euo pipefail

role="${CONTAINER_ROLE:-app}"
run_migrations="${RUN_MIGRATIONS:-false}"

echo "AssetBee: starting container (role=${role})"

php artisan config:cache --ansi
php artisan route:cache --ansi
php artisan view:cache --ansi
php artisan event:cache --ansi 2>/dev/null || true

if [[ ! -L public/storage ]]; then
    php artisan storage:link --ansi --force 2>/dev/null || true
fi

if [[ "${run_migrations}" == "true" && "${role}" == "app" ]]; then
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
