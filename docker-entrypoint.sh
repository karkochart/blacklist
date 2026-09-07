#!/bin/bash
set -e

# Mounted volume is owned by the host user — let git/composer work inside it
git config --global --add safe.directory '*' 2>/dev/null || true

is_root() { [ "$(id -u)" = "0" ]; }
as_www() { if is_root; then gosu www-data "$@"; else "$@"; fi; }

APP_DIR=/var/www/html

# Install PHP deps on first boot (only once a composer.json is present)
if [ -f "$APP_DIR/composer.json" ] && [ ! -f "$APP_DIR/vendor/autoload.php" ]; then
    echo "[entrypoint] vendor/ missing — running composer install"
    composer install --no-interaction --optimize-autoloader --working-dir="$APP_DIR"
    is_root && chown -R www-data:www-data "$APP_DIR/vendor"
fi

# Writable var/ (Symfony cache, logs, sessions)
if is_root && [ -d "$APP_DIR/var" ]; then
    chown -R www-data:www-data "$APP_DIR/var"
    chmod -R 775 "$APP_DIR/var"
fi

# Symfony console housekeeping on web-container boot (skipped until bin/console exists)
if [ "$1" = "apache2-foreground" ] && [ -f "$APP_DIR/bin/console" ]; then
    as_www php "$APP_DIR/bin/console" cache:clear --no-interaction || true
    as_www php "$APP_DIR/bin/console" doctrine:database:create --if-not-exists --no-interaction || true
    if ls "$APP_DIR"/migrations/Version*.php >/dev/null 2>&1; then
        as_www php "$APP_DIR/bin/console" doctrine:migrations:migrate --no-interaction --allow-no-migration || true
    fi
fi

# Apache drops privileges itself; anything else runs as www-data
if is_root && [ "$1" != "apache2-foreground" ]; then
    exec gosu www-data "$@"
fi

exec "$@"
