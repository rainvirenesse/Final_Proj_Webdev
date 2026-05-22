#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

export APP_ENV="${APP_ENV:-prod}"
export APP_DEBUG="${APP_DEBUG:-0}"
export APP_SECRET="${APP_SECRET:-build-time-secret-change-in-railway}"
export DATABASE_URL="${DATABASE_URL:-mysql://build:build@127.0.0.1:3306/build?serverVersion=8.0&charset=utf8mb4}"
export APP_URL="${APP_URL:-http://localhost}"
export DEFAULT_URI="${DEFAULT_URI:-$APP_URL}"
export CORS_ALLOW_ORIGIN="${CORS_ALLOW_ORIGIN:-'^https?://.*'}"
export JWT_PASSPHRASE="${JWT_PASSPHRASE:-build-time-passphrase}"
export JWT_SECRET_KEY="${JWT_SECRET_KEY:-config/jwt/private.pem}"
export JWT_PUBLIC_KEY="${JWT_PUBLIC_KEY:-config/jwt/public.pem}"
export MESSENGER_TRANSPORT_DSN="${MESSENGER_TRANSPORT_DSN:-doctrine://default?queue_name=messages}"
export MAILER_DSN="${MAILER_DSN:-null://null}"

# Docker/Railway builds run as root; Composer disables plugins unless this is set.
# Symfony Flex + Runtime plugins are required (vendor/autoload_runtime.php).
export COMPOSER_ALLOW_SUPERUSER=1

echo "==> Composer install (production)"
composer install \
    --no-dev \
    --optimize-autoloader \
    --classmap-authoritative \
    --no-interaction

bash bin/railway-jwt-keys.sh

echo "==> Symfony production bootstrap"
php bin/console assets:install public --env=prod --no-interaction
php bin/console importmap:install --env=prod --no-interaction || true

php bin/console cache:clear --env=prod --no-warmup --no-interaction
php bin/console cache:warmup --env=prod --no-interaction

echo "==> Railway build complete"
