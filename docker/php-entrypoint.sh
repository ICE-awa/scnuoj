#!/bin/sh
set -eu

VENDOR_SRC="/var/composer/vendor"
VENDOR_DST="/var/www/html/vendor"
LOCK_SRC="/var/composer/composer.lock"
LOCK_MARKER="${VENDOR_DST}/.composer.lock.sha256"

mkdir -p "$VENDOR_DST"

current_hash=""
if [ -f "$LOCK_SRC" ]; then
    current_hash="$(sha256sum "$LOCK_SRC" | awk '{print $1}')"
fi

installed_hash=""
if [ -f "$LOCK_MARKER" ]; then
    installed_hash="$(cat "$LOCK_MARKER")"
fi

if [ ! -f "${VENDOR_DST}/autoload.php" ] || [ "$current_hash" != "$installed_hash" ]; then
    echo "Initializing composer vendor volume..."
    find "$VENDOR_DST" -mindepth 1 -maxdepth 1 -exec rm -rf {} +
    cp -a "${VENDOR_SRC}/." "$VENDOR_DST/"

    if [ -n "$current_hash" ]; then
        echo "$current_hash" > "$LOCK_MARKER"
    fi
fi

echo "Checking SCNUOJ database initialization..."
php yii install/auto

exec php-fpm
