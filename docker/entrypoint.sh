#!/bin/sh
set -e

# Fix permissions when bind-mounting a host volume over storage (optional).
chmod -R ug+rwx storage bootstrap/cache 2>/dev/null || true

# Optional: migrate on container start when RUN_MIGRATIONS=true
if [ "${RUN_MIGRATIONS:-}" = "true" ]; then
    php artisan migrate --force --no-interaction
fi

exec docker-php-entrypoint apache2-foreground "$@"
