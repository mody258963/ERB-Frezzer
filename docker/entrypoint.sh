#!/bin/sh
set -e

chmod -R ug+rwx storage bootstrap/cache 2>/dev/null || true

if [ ! -f storage/oauth-private.key ]; then
    php artisan passport:keys --force --no-interaction
fi

if [ "${RUN_MIGRATIONS:-}" = "true" ]; then
    php artisan migrate --force --no-interaction
    php artisan db:seed --class=Database\\Seeders\\PassportClientSeeder --force --no-interaction
fi

exec docker-php-entrypoint apache2-foreground "$@"
