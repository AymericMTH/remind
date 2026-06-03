#!/bin/sh
# First-boot bootstrap for Re:Mind. Idempotent — safe to run on every container start.
# - Copies .env.example -> .env if missing.
# - Generates APP_KEY if empty.
# - Creates an empty database/database.sqlite if missing.
# - Runs `migrate --force` and `db:seed --force` (idempotent: User::updateOrCreate by email).
# After bootstrap, hands off to the standard FrankenPHP / PHP entrypoint with the original CMD.

set -e
cd /app

if [ ! -f .env ]; then
    echo "[entrypoint] Creating .env from .env.example"
    cp .env.example .env
fi

if ! grep -qE '^APP_KEY=base64:' .env; then
    echo "[entrypoint] Generating APP_KEY"
    php artisan key:generate --force --no-interaction
fi

if [ ! -f database/database.sqlite ]; then
    echo "[entrypoint] Creating empty SQLite database"
    mkdir -p database
    touch database/database.sqlite
fi

echo "[entrypoint] Running migrations"
php artisan migrate --force --no-interaction

echo "[entrypoint] Seeding default user (idempotent)"
php artisan db:seed --force --no-interaction

exec docker-php-entrypoint "$@"
