#!/usr/bin/env bash
# Wait for Railway MySQL and run migrations (retries on cold start).
# Set FORCE_REMIGRATE=true in Railway Variables to drop all tables and re-run all migrations.
set -euo pipefail

MAX_ATTEMPTS="${MIGRATE_MAX_ATTEMPTS:-15}"
SLEEP_SECONDS="${MIGRATE_SLEEP_SECONDS:-2}"
FORCE_REMIGRATE="${FORCE_REMIGRATE:-false}"

# ── Optional: drop schema and remigrate from scratch ──────────────────────────
if [[ "${FORCE_REMIGRATE}" == "true" ]]; then
    echo "===> FORCE_REMIGRATE=true: dropping all tables and re-running all migrations..."
    attempt=1
    while [[ "$attempt" -le "$MAX_ATTEMPTS" ]]; do
        if php bin/console doctrine:schema:drop --force --full-database --no-interaction --env=prod 2>&1; then
            echo "===> Schema dropped successfully."
            break
        fi
        echo "===> Drop attempt ${attempt}/${MAX_ATTEMPTS} failed; retrying in ${SLEEP_SECONDS}s..."
        sleep "$SLEEP_SECONDS"
        attempt=$((attempt + 1))
    done
    if [[ "$attempt" -gt "$MAX_ATTEMPTS" ]]; then
        echo "ERROR: Schema drop failed after ${MAX_ATTEMPTS} attempts." >&2
        exit 1
    fi
fi
# ──────────────────────────────────────────────────────────────────────────────

attempt=1
db_created=false
while [[ "$attempt" -le "$MAX_ATTEMPTS" ]]; do
    if php bin/console doctrine:database:create --if-not-exists --env=prod; then
        echo "==> Database exists or created successfully."
        db_created=true
        break
    fi
    echo "==> Database creation attempt ${attempt}/${MAX_ATTEMPTS} failed; retrying in ${SLEEP_SECONDS}s..."
    sleep "$SLEEP_SECONDS"
    attempt=$((attempt + 1))
done

if [[ "$db_created" == "false" ]]; then
    echo "ERROR: Database creation failed after ${MAX_ATTEMPTS} attempts." >&2
    exit 1
fi

attempt=1
migrated=false
while [[ "$attempt" -le "$MAX_ATTEMPTS" ]]; do
    if php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration --env=prod; then
        echo "==> Migrations complete"
        migrated=true
        break
    fi

    echo "==> Migration attempt ${attempt}/${MAX_ATTEMPTS} failed; retrying in ${SLEEP_SECONDS}s..."
    sleep "$SLEEP_SECONDS"
    attempt=$((attempt + 1))
done

if [[ "$migrated" == "false" ]]; then
    echo "ERROR: Migrations failed after ${MAX_ATTEMPTS} attempts." >&2
    echo "  If logs mention MESSENGER_TRANSPORT_DSN or MAILER_DSN, redeploy latest code or add those variables in Railway." >&2
    echo "  If logs mention Connection refused, fix DATABASE_URL (Reference → MySQL → MYSQL_URL)." >&2
    exit 1
fi

if [[ "${LOAD_FIXTURES:-false}" == "true" ]]; then
    echo "==> LOAD_FIXTURES=true: loading fixtures..."
    php bin/console doctrine:fixtures:load --no-interaction --env=prod || echo "WARNING: Fixture loading failed."
fi

exit 0