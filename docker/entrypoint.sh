#!/bin/sh
set -e

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
        chmod 644 storage/oauth-public.key
    fi

    chown -R www-data:www-data storage bootstrap/cache

    find storage bootstrap/cache -type d -exec chmod 775 {} \;
    find storage bootstrap/cache -type f ! -name 'oauth-private.key' ! -name 'oauth-public.key' -exec chmod 664 {} \;

    if [ -f storage/oauth-private.key ]; then
        chmod 660 storage/oauth-private.key
        chown www-data:www-data storage/oauth-private.key
    fi
    if [ -f storage/oauth-public.key ]; then
        chmod 644 storage/oauth-public.key
        chown www-data:www-data storage/oauth-public.key
    fi
}

if [ ! -f storage/oauth-private.key ]; then
    php artisan passport:keys --force --no-interaction
fi

fix_storage_permissions

if [ "${RUN_MIGRATIONS:-}" = "true" ]; then
    php artisan migrate --force --no-interaction
    php artisan db:seed --class=Database\\Seeders\\PassportClientSeeder --force --no-interaction
fi

exec docker-php-entrypoint apache2-foreground "$@"
