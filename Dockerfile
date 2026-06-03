# Dockerfile
FROM dunglas/frankenphp:1-php8.4

# pdo_sqlite + Composer + Node 20 + npm + unzip + git
RUN install-php-extensions pdo_sqlite \
 && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
 && apt-get update && apt-get install -y --no-install-recommends nodejs npm git unzip \
 && rm -rf /var/lib/apt/lists/*

WORKDIR /app

# 1. Composer deps first, no autoload, no scripts — best layer cache while iterating on app code.
#    Dev deps included on purpose: this is a local single-user image; dev tooling (Pest, Pint,
#    laravel/boost) is registered in bootstrap/cache and the app fails to boot without it.
COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress

# 2. npm deps next (same caching rationale).
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

# 3. Full source.
COPY . /app
COPY docker-entrypoint.sh /usr/local/bin/remind-entrypoint
RUN chmod +x /usr/local/bin/remind-entrypoint

# 4. Now that source is in, dump composer autoloader (post-autoload scripts can resolve App\ classes).
RUN composer dump-autoload --optimize --no-interaction

# 5. Wayfinder (called from the vite plugin during npm run build) boots Laravel, which needs:
#    - .env  → seed from .env.example (runtime entrypoint won't overwrite it).
#    - storage/framework/{cache,views,sessions} → .dockerignore'd, so recreate them here.
RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs \
 && cp .env.example .env \
 && php artisan key:generate --force --no-interaction

# 6. Build frontend assets (generates resources/js/{actions,routes}/ via Wayfinder + public/build/).
RUN npm run build \
 && rm -f public/hot

# Caddy needs to bind low ports; FrankenPHP's frankenphp user handles this.
ENV SERVER_NAME=:8000
EXPOSE 8000

ENTRYPOINT ["/usr/local/bin/remind-entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]
