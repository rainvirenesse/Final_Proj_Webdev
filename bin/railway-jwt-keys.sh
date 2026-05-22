#!/usr/bin/env bash
# Materialize JWT key files for Lexik (Railway has no committed PEMs).
set -euo pipefail

JWT_DIR="${JWT_DIR:-config/jwt}"
mkdir -p "$JWT_DIR"

if [[ -n "${JWT_PRIVATE_KEY_B64:-}" && -n "${JWT_PUBLIC_KEY_B64:-}" ]]; then
    echo "$JWT_PRIVATE_KEY_B64" | base64 -d > "$JWT_DIR/private.pem"
    echo "$JWT_PUBLIC_KEY_B64" | base64 -d > "$JWT_DIR/public.pem"
    chmod 600 "$JWT_DIR/private.pem"
    chmod 644 "$JWT_DIR/public.pem"
    echo "JWT keys written from JWT_*_B64 environment variables."
    exit 0
fi

if [[ -f "$JWT_DIR/private.pem" && -f "$JWT_DIR/public.pem" ]]; then
    echo "JWT keys already present."
    exit 0
fi

if [[ -z "${JWT_PASSPHRASE:-}" ]]; then
    echo "ERROR: Set JWT_PASSPHRASE (and optionally JWT_PRIVATE_KEY_B64 / JWT_PUBLIC_KEY_B64) in Railway variables." >&2
    exit 1
fi

php bin/console lexik:jwt:generate-keypair --skip-if-exists
echo "JWT keypair generated (new deploys invalidate existing tokens unless B64 keys are set)."
