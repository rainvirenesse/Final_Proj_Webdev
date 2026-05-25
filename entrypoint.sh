#!/bin/bash
set -e

cd /app

export APP_ENV="${APP_ENV:-prod}"
export APP_DEBUG="${APP_DEBUG:-0}"
export COMPOSER_ALLOW_SUPERUSER=1
export MESSENGER_TRANSPORT_DSN="${MESSENGER_TRANSPORT_DSN:-doctrine://default?queue_name=messages}"
export MAILER_DSN="${MAILER_DSN:-null://null}"
export GOOGLE_CLIENT_ID="${GOOGLE_CLIENT_ID:-}"
export GOOGLE_CLIENT_SECRET="${GOOGLE_CLIENT_SECRET:-}"
export GOOGLE_OAUTH_REDIRECT_URI="${GOOGLE_OAUTH_REDIRECT_URI:-http://localhost}"
export CONTACT_FORM_EMBED_URL="${CONTACT_FORM_EMBED_URL:-}"
export CORS_ALLOW_ORIGIN="${CORS_ALLOW_ORIGIN:-^https?://.*}"

if [ -z "$DATABASE_URL" ] && [ -n "$MYSQL_URL" ]; then
    export DATABASE_URL="$MYSQL_URL"
    echo "Exported DATABASE_URL from MYSQL_URL"
fi

if [ -z "${APP_SECRET:-}" ]; then
    echo "ERROR: Set APP_SECRET in Railway variables." >&2
    exit 1
fi

if [ -z "${DATABASE_URL:-}" ]; then
    echo "ERROR: Set DATABASE_URL (Reference → MySQL → MYSQL_URL)." >&2
    exit 1
fi

if [ -z "${JWT_PASSPHRASE:-}" ]; then
    echo "ERROR: Set JWT_PASSPHRASE in Railway variables." >&2
    exit 1
fi

bash bin/railway-jwt-keys.sh

mkdir -p var/cache var/log public/images/products public/uploads/products
chown -R www-data:www-data var public/images/products public/uploads/products 2>/dev/null || true

echo "Running database migrations..."
bash bin/railway-migrate.sh

echo "Warming cache..."
php bin/console cache:warmup --env=prod --no-interaction

PORT="${PORT:-8080}"
echo "Configuring Nginx to listen on port ${PORT}"
sed "s/__PORT__/${PORT}/g" /etc/nginx/conf.d/symfony.conf.template > /etc/nginx/conf.d/symfony.conf

echo "Starting PHP-FPM..."
php-fpm -D

echo "Starting Nginx..."
exec nginx -g "daemon off;"
