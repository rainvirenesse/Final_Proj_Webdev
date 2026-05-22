#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

export APP_ENV="${APP_ENV:-prod}"
export APP_DEBUG="${APP_DEBUG:-0}"

# Required by Symfony prod container; set in Railway Variables to override.
export MESSENGER_TRANSPORT_DSN="${MESSENGER_TRANSPORT_DSN:-doctrine://default?queue_name=messages}"
export MAILER_DSN="${MAILER_DSN:-null://null}"

if [[ -z "${APP_SECRET:-}" || "${APP_SECRET}" == "change-me-to-a-random-32-char-secret" ]]; then
    echo "ERROR: Set APP_SECRET in Railway service variables." >&2
    exit 1
fi

if [[ -z "${DATABASE_URL:-}" ]]; then
    echo "ERROR: DATABASE_URL is not set on this Railway service." >&2
    echo "  Fix: web service → Variables → New Variable → Reference → MySQL → MYSQL_URL" >&2
    echo "  Or set: mysql://\${{MYSQLUSER}}:\${{MYSQLPASSWORD}}@\${{MYSQLHOST}}:\${{MYSQLPORT}}/\${{MYSQLDATABASE}}?serverVersion=8.0.31&charset=utf8mb4" >&2
    exit 1
fi

if [[ "${DATABASE_URL}" == *"@127.0.0.1"* || "${DATABASE_URL}" == *"@localhost"* || "${DATABASE_URL}" == *"//build:build@"* ]]; then
    echo "ERROR: DATABASE_URL points to localhost/build placeholder, not Railway MySQL." >&2
    echo "  Current value starts with: ${DATABASE_URL%%@*}@..." >&2
    echo "  Fix: Variables → DATABASE_URL → Reference → select your MySQL service → MYSQL_URL" >&2
    exit 1
fi

export APP_URL="${APP_URL:-}"
export DEFAULT_URI="${DEFAULT_URI:-$APP_URL}"

if [[ -z "${APP_URL}" ]]; then
    echo "WARNING: APP_URL is not set — product imageUrl values may be wrong for the mobile app." >&2
fi

bash bin/railway-jwt-keys.sh

mkdir -p var/cache var/log public/images/products public/uploads/products
chmod -R ug+rwx var public/images/products public/uploads/products 2>/dev/null || true

echo "==> Running database migrations (host: $(php -r 'echo parse_url(getenv("DATABASE_URL"), PHP_URL_HOST) ?: "unknown";'))"
bash bin/railway-migrate.sh

echo "==> Warming cache"
php bin/console cache:warmup --env=prod --no-interaction

PORT="${PORT:-8000}"
echo "==> Starting PHP built-in server on 0.0.0.0:${PORT} (Symfony router: public/index.php)"
# Without public/index.php, /api/products and /health return 404 and Railway healthcheck fails.
exec php -S "0.0.0.0:${PORT}" -t public public/index.php
