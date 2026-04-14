FROM php:8.3-fpm-alpine

# ── System dependencies ──────────────────────────────────────────────────────
RUN apk add --no-cache \
        nginx \
        postgresql-dev \
        libpq \
        supervisor \
        curl \
    && docker-php-ext-install \
        pdo \
        pdo_pgsql \
        opcache \
        pcntl

# ── Composer ─────────────────────────────────────────────────────────────────
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# ── App ───────────────────────────────────────────────────────────────────────
WORKDIR /var/www

COPY . .

# Install PHP dependencies (production only, no dev packages)
RUN composer install \
        --no-dev \
        --optimize-autoloader \
        --no-interaction \
        --prefer-dist

# Ensure Laravel's writable directories exist and are owned by www-data
RUN mkdir -p storage/framework/{sessions,views,cache} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# ── Config ────────────────────────────────────────────────────────────────────
COPY docker/nginx.conf    /etc/nginx/nginx.conf
COPY docker/start.sh      /start.sh
RUN chmod +x /start.sh

EXPOSE 80

CMD ["/start.sh"]
