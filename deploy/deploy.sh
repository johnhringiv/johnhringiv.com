#!/bin/sh
# Rolling deploy for johnhringiv.com's two-container HAProxy setup (no compose).
#
# The host runs a primary and a backup app container behind HAProxy (pfSense);
# HAProxy serves the primary and fails over to the backup when the primary's
# /nginx-health check stops answering. This script recreates them ONE AT A
# TIME — backup first, then primary — health-gating each before touching the
# next, so at least one instance is always serving. That is the zero-downtime
# story: while one container restarts on the new image, HAProxy keeps serving
# from the other.
#
# Usage:
#   1. Copy .env.example to a private env file next to this script, fill it in,
#      chmod 600 it.
#   2. Optionally place a saved image tarball next to it (docker save output).
#   3. sh deploy.sh [env-file] [image-tarball]
#
# Idempotent: re-running recreates both containers on the current image.
set -e

ENV_FILE="${1:-.env}"
IMAGE_TAR="${2:-}"

[ -f "$ENV_FILE" ] || { echo "missing $ENV_FILE (copy .env.example and fill it in)"; exit 1; }

# shellcheck disable=SC1090
. "$ENV_FILE"

IMAGE="${IMAGE:-ghcr.io/johnhringiv/johnhringiv.com:prod}"
CONTAINER_PRIMARY="${CONTAINER_PRIMARY:-johnhringiv-primary}"
CONTAINER_BACKUP="${CONTAINER_BACKUP:-johnhringiv-backup}"
PORT_PRIMARY="${PORT_PRIMARY:-8080}"
PORT_BACKUP="${PORT_BACKUP:-8081}"
HEALTH_PATH="${HEALTH_PATH:-/nginx-health}"

if [ -n "$IMAGE_TAR" ]; then
    docker load -i "$IMAGE_TAR"
else
    docker pull "$IMAGE" || echo "pull failed; will use local image $IMAGE if present"
fi

# Recreate one container and wait for it to answer /nginx-health locally.
# Returns non-zero (and, under set -e, aborts the whole deploy) if it never
# comes up — so a broken image can't take down both instances.
deploy_one() {
    name="$1"
    port="$2"
    echo "$(date -u +%FT%TZ) recreating $name on :$port"
    docker rm -f "$name" 2>/dev/null || true
    docker run -d --name "$name" --restart unless-stopped \
        -p "$port:8080" "$IMAGE" >/dev/null
    i=0
    while [ $i -lt 24 ]; do  # up to ~2 min
        if curl -sf -m 5 "http://localhost:$port$HEALTH_PATH" >/dev/null 2>&1; then
            echo "$(date -u +%FT%TZ) $name healthy"
            return 0
        fi
        i=$((i + 1))
        sleep 5
    done
    echo "$(date -u +%FT%TZ) FAILED: $name did not become healthy on :$port"
    return 1
}

# Backup first so the primary keeps serving the old image throughout. If the
# backup never comes up, set -e aborts here BEFORE the primary is touched —
# the site stays up on the old primary and this run is a retryable no-op.
deploy_one "$CONTAINER_BACKUP" "$PORT_BACKUP"
# Now the primary. HAProxy fails over to the (updated, healthy) backup while
# the primary is down, then returns to the primary once it's healthy again.
deploy_one "$CONTAINER_PRIMARY" "$PORT_PRIMARY"

docker image prune -f >/dev/null 2>&1 || true

docker ps --filter "name=$CONTAINER_PRIMARY" --filter "name=$CONTAINER_BACKUP" \
    --format '{{.Names}}: {{.Status}}'
echo "$(date -u +%FT%TZ) deployed $IMAGE"
[ -n "$PUBLIC_URL" ] && echo "verify: curl $PUBLIC_URL$HEALTH_PATH"
exit 0
