#!/usr/bin/env bash
# Wait for Railway MySQL and run migrations (retries on cold start).
set -euo pipefail

MAX_ATTEMPTS="${MIGRATE_MAX_ATTEMPTS:-30}"
SLEEP_SECONDS="${MIGRATE_SLEEP_SECONDS:-2}"

attempt=1
while [[ "$attempt" -le "$MAX_ATTEMPTS" ]]; do
    if php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration --env=prod; then
        echo "==> Migrations complete"
        exit 0
    fi

    echo "==> Migration attempt ${attempt}/${MAX_ATTEMPTS} failed; retrying in ${SLEEP_SECONDS}s..."
    sleep "$SLEEP_SECONDS"
    attempt=$((attempt + 1))
done

echo "ERROR: Could not connect to MySQL after ${MAX_ATTEMPTS} attempts." >&2
exit 1
