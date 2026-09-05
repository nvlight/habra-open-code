#!/usr/bin/env bash
#
# Reloads the nginx container whenever the on-disk TLS certificate changes.
#
# certbot renews certificates in place (shortlived profile, every ~7 days) but
# nginx holds the certificate in memory and keeps serving the OLD one until it
# is reloaded. This script compares the fingerprint of the certificate on disk
# against the last reloaded one and triggers `docker compose exec nginx
# nginx -s reload` when it changes.
#
# Runs from a host systemd timer (cert-nginx-reload.timer) as user "dk"
# (member of the "docker" group).

set -euo pipefail

APP_DIR="/opt/app"
CERT_FILE="${APP_DIR}/docker/prod/letsencrypt/live/5.188.31.27/fullchain.pem"
STATE_FILE="/opt/app/docker/prod/.cert-reload-state"
LOG_TAG="reload-nginx-on-renew"

state_dir="$(dirname "$STATE_FILE")"

if [[ ! -f "$CERT_FILE" ]]; then
    logger -t "$LOG_TAG" "certificate file not found: $CERT_FILE"
    exit 1
fi

# Current fingerprint of the certificate on disk
current_fp="$(openssl x509 -in "$CERT_FILE" -noout -fingerprint -sha256)"

mkdir -p "$state_dir"

# First run: initialize the state with the current cert and (idempotently)
# reload once so the currently-served certificate matches disk.
if [[ ! -f "$STATE_FILE" || "$(cat "$STATE_FILE")" != "$current_fp" ]]; then
    if cd "$APP_DIR" && docker compose -f docker-compose.prod.yml exec -T nginx nginx -s reload >/dev/null 2>&1; then
        printf "%s" "$current_fp" > "$STATE_FILE"
        logger -t "$LOG_TAG" "nginx reloaded after cert change (fp changed)"
    else
        logger -t "$LOG_TAG" "nginx reload FAILED after cert change"
        exit 1
    fi
else
    logger -t "$LOG_TAG" "cert unchanged, no reload needed"
fi