#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

export APP_ENV="${APP_ENV:-prod}"
export APP_DEBUG="${APP_DEBUG:-0}"

if [[ -z "${APP_SECRET:-}" || "${APP_SECRET}" == "change-me-to-a-random-32-char-secret" ]]; then
    echo "ERROR: Set APP_SECRET in Railway service variables." >&2
    exit 1
fi

if [[ -z "${DATABASE_URL:-}" ]]; then
    echo "ERROR: Set DATABASE_URL (use Railway MySQL MYSQL_URL reference)." >&2
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

echo "==> Running database migrations"
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration --env=prod

echo "==> Warming cache"
php bin/console cache:warmup --env=prod --no-interaction

PORT="${PORT:-8000}"
echo "==> Starting PHP built-in server on 0.0.0.0:${PORT}"
exec php -S "0.0.0.0:${PORT}" -t public
