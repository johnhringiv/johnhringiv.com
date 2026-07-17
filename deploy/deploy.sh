#!/bin/sh
# Rolling deploy for johnhringiv.com's two-container HAProxy setup (no compose).
#
# No config file: the site has no secrets and there is exactly one production
# host, so the values below are hardcoded as defaults. Every one is overridable
# by an env var for one-offs — e.g. roll back with
#   IMAGE=ghcr.io/johnhringiv/johnhringiv.com:sha-abc123 sh deploy.sh
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
#   sh deploy.sh                 # pull IMAGE and roll both containers
#   sh deploy.sh <image-tarball> # load a saved image instead of pulling (offline)
#
# Idempotent: re-running recreates both containers on the current image.
set -e

IMAGE_TAR="${1:-}"

IMAGE="${IMAGE:-ghcr.io/johnhringiv/johnhringiv.com:prod}"
CONTAINER_PRIMARY="${CONTAINER_PRIMARY:-johnhringiv-primary}"
CONTAINER_BACKUP="${CONTAINER_BACKUP:-johnhringiv-backup}"
PORT_PRIMARY="${PORT_PRIMARY:-8080}"   # HAProxy primary backend
PORT_BACKUP="${PORT_BACKUP:-8086}"     # HAProxy backup backend
HEALTH_PATH="${HEALTH_PATH:-/nginx-health}"
PUBLIC_URL="${PUBLIC_URL:-https://johnhringiv.com}"

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
