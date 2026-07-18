#!/bin/sh
# Poll the :latest image tag and redeploy when its digest changes — and
# self-heal: an unchanged tag with a container not running (typical after a
# host reboot) also triggers a redeploy, which recreates both containers.
#
# No config file: the site has no secrets and there is one production host, so
# the values below are hardcoded as defaults, each overridable by an env var.
#
# Runs from cron on the production host (the host has no inbound access, so
# deploys are pull-based). An unchanged, healthy tag costs one manifest check
# and one edge /health probe; layers only download when CI has published a new
# :latest, i.e. after a push to main. This is continuous deploy from main.
#
#   sh poll-deploy.sh
#
# Exit codes: 0 = no-op or successful deploy; 1 = unhealthy (a deploy ran but
# the edge never came up, or the containers are running yet the edge is
# unreachable — Cloudflare/HAProxy/DNS trouble a redeploy wouldn't fix).
# Configure cron/mail to notify on non-zero exit.
set -e

DIR=$(dirname "$0")

IMAGE="${IMAGE:-ghcr.io/johnhringiv/johnhringiv.com:latest}"
CONTAINER_PRIMARY="${CONTAINER_PRIMARY:-johnhringiv-primary}"
CONTAINER_BACKUP="${CONTAINER_BACKUP:-johnhringiv-backup}"
HEALTH_PATH="${HEALTH_PATH:-/nginx-health}"
PUBLIC_URL="${PUBLIC_URL:-https://johnhringiv.com}"

container_running() {
    [ "$(docker inspect "$1" --format '{{.State.Running}}' 2>/dev/null)" = "true" ]
}

# Three attempts, 5s apart — one blip shouldn't page or churn containers.
edge_healthy() {
    [ -n "$PUBLIC_URL" ] || return 0
    i=0
    while [ $i -lt 3 ]; do
        curl -sf -m 5 "$PUBLIC_URL$HEALTH_PATH" >/dev/null 2>&1 && return 0
        i=$((i + 1))
        sleep 5
    done
    return 1
}

# Before CI's first publish the tag may not exist; that is not an error.
if ! docker pull -q "$IMAGE" >/dev/null 2>&1; then
    echo "$(date -u +%FT%TZ) pull failed for $IMAGE (tag not published yet?); skipping"
    exit 0
fi

latest=$(docker image inspect "$IMAGE" --format '{{.Id}}')
running=$(docker inspect "$CONTAINER_PRIMARY" --format '{{.Image}}' 2>/dev/null || echo none)

if [ "$latest" = "$running" ]; then
    if container_running "$CONTAINER_PRIMARY" && container_running "$CONTAINER_BACKUP"; then
        if edge_healthy; then
            exit 0
        fi
        echo "$(date -u +%FT%TZ) UNHEALTHY: containers running but ${PUBLIC_URL}${HEALTH_PATH} unreachable (Cloudflare/HAProxy/DNS?); not redeploying"
        exit 1
    fi
    echo "$(date -u +%FT%TZ) a container is not running on unchanged image (host rebooted?); redeploying"
else
    echo "$(date -u +%FT%TZ) new image for $IMAGE (running=$running -> $latest); deploying"
fi

# deploy.sh health-gates each container locally as it recreates them.
sh "$DIR/deploy.sh"

# Confirm the edge too (deploy.sh only checks localhost).
if [ -n "$PUBLIC_URL" ]; then
    if edge_healthy; then
        echo "$(date -u +%FT%TZ) deploy healthy at edge"
        exit 0
    fi
    echo "$(date -u +%FT%TZ) DEPLOY UNHEALTHY: ${PUBLIC_URL}${HEALTH_PATH} not responding"
    exit 1
fi
echo "$(date -u +%FT%TZ) deployed (no PUBLIC_URL; edge check skipped)"
