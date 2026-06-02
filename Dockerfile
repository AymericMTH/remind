# Dockerfile
FROM dunglas/frankenphp:1-php8.4

# pdo_sqlite + Composer + Node + npm + unzip (required by Composer)
RUN install-php-extensions pdo_sqlite \
 && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
 && apt-get update && apt-get install -y --no-install-recommends nodejs npm git unzip \
 && rm -rf /var/lib/apt/lists/*

WORKDIR /app
COPY . /app

# Caddy needs to bind low ports; FrankenPHP's frankenphp user handles this.
ENV SERVER_NAME=:8000
EXPOSE 8000

CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]
