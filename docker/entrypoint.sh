#!/bin/sh
set -e

# Dokploy / Docker often inject APP_KEY="" which overrides .env and breaks encryption.
ensure_app_key() {
    case "${APP_KEY:-}" in
        ''|'base64:') unset APP_KEY ;;
    esac

    if [ ! -f .env ]; then
        cp .env.example .env
    fi

    if ! grep -qE '^APP_KEY=base64:[A-Za-z0-9+/=]+$' .env 2>/dev/null; then
        php artisan key:generate --force --no-interaction
    fi

    chown www-data:www-data .env 2>/dev/null || true
    chmod 664 .env 2>/dev/null || true
}

fix_storage_permissions() {
    mkdir -p \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache

    touch storage/logs/laravel.log 2>/dev/null || true

    if [ -f storage/oauth-private.key ]; then
        chmod 660 storage/oauth-private.key
    fi
    if [ -f storage/oauth-public.key ]; then
        chmod 660 storage/oauth-public.key
    fi

    chown -R www-data:www-data storage bootstrap/cache

    find storage bootstrap/cache -type d -exec chmod 775 {} \;
    find storage bootstrap/cache -type f ! -name 'oauth-private.key' ! -name 'oauth-public.key' -exec chmod 664 {} \;

    if [ -f storage/oauth-private.key ]; then
        chmod 660 storage/oauth-private.key
        chown www-data:www-data storage/oauth-private.key
    fi
    if [ -f storage/oauth-public.key ]; then
        chmod 660 storage/oauth-public.key
        chown www-data:www-data storage/oauth-public.key
    fi
}

ensure_app_key

if [ ! -f storage/oauth-private.key ]; then
    php artisan passport:keys --force --no-interaction
fi

fix_storage_permissions

mkdir -p storage/app/public/parts
chown -R www-data:www-data storage/app/public 2>/dev/null || true
chmod -R 775 storage/app/public 2>/dev/null || true

if [ ! -L public/storage ]; then
    php artisan storage:link --no-interaction
fi

if [ "${RUN_MIGRATIONS:-}" = "true" ]; then
    php artisan migrate --force --no-interaction
    php artisan db:seed --class=Database\\Seeders\\PassportClientSeeder --force --no-interaction
fi

exec docker-php-entrypoint apache2-foreground "$@"
