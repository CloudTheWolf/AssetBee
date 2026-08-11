#!/usr/bin/env bash
set -euo pipefail

APP_DIR=/var/www/html
MANIFEST="${APP_DIR}/public/build/manifest.json"
BUILD_WORK="/tmp/assetbee-vite-build"

mkdir -p \
    "${APP_DIR}/storage/framework/cache" \
    "${APP_DIR}/storage/framework/sessions" \
    "${APP_DIR}/storage/framework/testing" \
    "${APP_DIR}/storage/framework/views" \
    "${APP_DIR}/storage/logs" \
    "${APP_DIR}/bootstrap/cache" \
    "${APP_DIR}/public/build"

if [ -n "${WWWUSER:-}" ]; then
    usermod -u "${WWWUSER}" sail 2>/dev/null || true
    chown -R "${WWWUSER}" \
        "${APP_DIR}/storage/framework" \
        "${APP_DIR}/storage/logs" \
        "${APP_DIR}/bootstrap/cache" \
        2>/dev/null || true
fi

needs_frontend_build() {
    if [ ! -f "${MANIFEST}" ]; then
        return 0
    fi

    if find \
        "${APP_DIR}/resources/css" \
        "${APP_DIR}/resources/js" \
        "${APP_DIR}/vite.config.js" \
        -type f -newer "${MANIFEST}" 2>/dev/null | grep -q .; then
        return 0
    fi

    return 1
}

ensure_node_modules() {
    if [ -d "${APP_DIR}/node_modules/vite" ]; then
        return 0
    fi

    echo "Sail: installing npm dependencies ..."
    cd "${APP_DIR}"
    npm ci || npm install
}

build_frontend_on_local_disk() {
    # Tailwind/Vite on a Windows 9p bind-mount is extremely slow. Copy only the
    # inputs onto the container's local filesystem, build there, then sync out.
    rm -rf "${BUILD_WORK}"
    mkdir -p "${BUILD_WORK}/public/build" \
        "${BUILD_WORK}/vendor/livewire/flux/dist" \
        "${BUILD_WORK}/vendor/livewire/flux/stubs" \
        "${BUILD_WORK}/vendor/laravel/framework/src/Illuminate/Pagination/resources"

    cp "${APP_DIR}/package.json" "${APP_DIR}/vite.config.js" "${BUILD_WORK}/"
    if [ -f "${APP_DIR}/package-lock.json" ]; then
        cp "${APP_DIR}/package-lock.json" "${BUILD_WORK}/"
    fi

    cp -a "${APP_DIR}/resources" "${BUILD_WORK}/resources"
    cp -a "${APP_DIR}/vendor/livewire/flux/dist/." "${BUILD_WORK}/vendor/livewire/flux/dist/"
    cp -a "${APP_DIR}/vendor/livewire/flux/stubs/." "${BUILD_WORK}/vendor/livewire/flux/stubs/"
    cp -a "${APP_DIR}/vendor/laravel/framework/src/Illuminate/Pagination/resources/." \
        "${BUILD_WORK}/vendor/laravel/framework/src/Illuminate/Pagination/resources/"

    if [ -d "${APP_DIR}/vendor/livewire/flux-pro/stubs" ]; then
        mkdir -p "${BUILD_WORK}/vendor/livewire/flux-pro/stubs"
        cp -a "${APP_DIR}/vendor/livewire/flux-pro/stubs/." "${BUILD_WORK}/vendor/livewire/flux-pro/stubs/"
    fi

    ln -s "${APP_DIR}/node_modules" "${BUILD_WORK}/node_modules"

    echo "Sail: building frontend assets on container-local disk ..."
    (cd "${BUILD_WORK}" && npm run build)

    rm -rf "${APP_DIR}/public/build"
    mkdir -p "${APP_DIR}/public/build"
    cp -a "${BUILD_WORK}/public/build/." "${APP_DIR}/public/build/"
    rm -rf "${BUILD_WORK}"
}

if needs_frontend_build; then
    ensure_node_modules
    build_frontend_on_local_disk
    echo "Sail: frontend assets ready."
else
    ensure_node_modules
fi

exec /usr/local/bin/start-container "$@"
