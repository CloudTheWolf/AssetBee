#!/usr/bin/env bash
set -euo pipefail

role="${CONTAINER_ROLE:-app}"
run_migrations="${RUN_MIGRATIONS:-false}"

log() {
    echo "AssetBee: $*"
}

elapsed_ms() {
    local start_ns=$1
    local now_ns
    now_ns=$(date +%s%N)
    echo $(((now_ns - start_ns) / 1000000))
}

boot_start_ns=$(date +%s%N)

log "starting container (role=${role})"

# Config + routes must be cached at runtime so container env vars (especially
# APP_KEY) are applied. Livewire serves JS from /livewire-{hash}/… where the
# hash is derived from APP_KEY; a build-time route cache with a dummy key 404s.
# View/event caches and storage:link remain baked into the image.
step_ns=$(date +%s%N)
php artisan config:cache --ansi
log "config:cache finished in $(elapsed_ms "$step_ns")ms"

step_ns=$(date +%s%N)
php artisan route:cache --ansi
log "route:cache finished in $(elapsed_ms "$step_ns")ms"

wait_for_database() {
    local max_attempts="${DB_WAIT_ATTEMPTS:-20}"
    local sleep_seconds="${DB_WAIT_SLEEP:-1}"
    local attempt=1

    log "waiting for database"

    while (( attempt <= max_attempts )); do
        if php -r '
            $host = getenv("DB_HOST") ?: "127.0.0.1";
            $port = getenv("DB_PORT") ?: "3306";
            $database = getenv("DB_DATABASE") ?: "";
            $username = getenv("DB_USERNAME") ?: "";
            $password = getenv("DB_PASSWORD") ?: "";
            $timeout = (int) (getenv("DB_CONNECT_TIMEOUT") ?: 3);

            $dsn = sprintf(
                "mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4",
                $host,
                $port,
                $database,
            );

            $pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_TIMEOUT => $timeout,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            $pdo->query("SELECT 1");
        ' >/dev/null 2>&1; then
            log "database is ready (attempt ${attempt})"

            return 0
        fi

        log "database not ready (attempt ${attempt}/${max_attempts})"
        sleep "${sleep_seconds}"
        attempt=$((attempt + 1))
    done

    echo "AssetBee: database did not become ready in time" >&2

    return 1
}

if [[ "${run_migrations}" == "true" && "${role}" == "app" ]]; then
    step_ns=$(date +%s%N)
    wait_for_database
    log "database wait finished in $(elapsed_ms "$step_ns")ms"

    step_ns=$(date +%s%N)
    log "running database migrations"
    php artisan migrate --force --ansi
    log "migrations finished in $(elapsed_ms "$step_ns")ms"
fi

log "startup work finished in $(elapsed_ms "$boot_start_ns")ms; launching ${role}"

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
