# syntax=docker/dockerfile:1
#
# =============================================================================
# Laravel (ERB Frezzer) — production-style image with Vite assets and Apache.
#
# Typical first-time deployment (run on server / in CI/CD, via Compose, etc.):
#
#   php artisan migrate --force
#
# Generate APP_KEY once if missing (.env): 
#
#   php artisan key:generate --show   # prints a key → set APP_KEY=... in .env
#
# Cache for production traffic (after .env exists):
#
#   php artisan config:cache && php artisan route:cache && php artisan view:cache
#
# Laravel Sanctum (this repo): migrations include personal_access_tokens.
# Publish custom config only if needed:
#   php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
#
# Laravel Passport ONLY if you add OAuth2 / password grant (not bundled here):
#
#   composer require laravel/passport
#   php artisan migrate --force
#   php artisan passport:keys --force
#   php artisan passport:install --force
#
# Optional on container boot: set env RUN_MIGRATIONS=true to run migrate automatically.
#
# Build: docker build -t frostparts-app:latest .
#   Optional: docker build --target production -t frostparts-app:latest .
#
# Run:   docker run -p 8080:80 --env-file .env frostparts-app:latest
#
# =============================================================================

#
# ── Frontend (Vite / Tailwind) ───────────────────────────────────────────────
FROM node:22-bookworm-slim AS node

WORKDIR /assets

COPY package.json ./
RUN npm install --no-audit --no-fund

COPY vite.config.js ./
COPY resources ./resources
COPY public ./public

RUN npm run build

#
# ── PHP + Apache (runtime) ────────────────────────────────────────────────────
FROM php:8.2-apache-bookworm AS production

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update \
 && apt-get install -y --no-install-recommends \
        git curl zip unzip libicu-dev libzip-dev libpng-dev libonig-dev libxml2-dev \
 && docker-php-ext-configure zip \
 && docker-php-ext-install -j"$(nproc)" intl pdo_mysql mbstring zip bcmath opcache pcntl exif gd \
 && pecl install redis \
 && docker-php-ext-enable redis \
 && a2enmod rewrite headers expires \
 && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Application source (respects .dockerignore — excludes vendor/, node_modules/, etc.).
COPY --chown=www-data:www-data . .

COPY --from=node --chown=www-data:www-data /assets/public/build ./public/build

RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache

COPY docker/000-laravel.conf /etc/apache2/sites-available/laravel.conf
COPY docker/entrypoint.sh /usr/local/bin/docker-laravel-entrypoint

RUN chmod +x /usr/local/bin/docker-laravel-entrypoint \
 && a2dissite 000-default.conf \
 && a2ensite laravel.conf

RUN composer install \
        --no-dev \
        --no-interaction \
        --no-ansi \
        --optimize-autoloader \
        --no-scripts

RUN composer dump-autoload --optimize --no-dev --no-interaction --quiet

ENTRYPOINT ["docker-laravel-entrypoint"]
EXPOSE 80
