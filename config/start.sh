#!/bin/sh
# nginx + PHP-FPM under one shell (tini's child). Both run in the BACKGROUND so
# the shell blocks in `wait`, where a trapped SIGTERM can actually fire. With
# nginx in the foreground (the old approach) the trap could never run, so
# `docker stop` timed out and the container was SIGKILLed (exit 137) — which
# Synology Container Manager reports as "stopped unexpectedly". Here SIGTERM
# gracefully shuts both down and the container exits 0 (like a well-behaved
# single-process container).

term() {
    kill -QUIT "$nginx_pid" 2>/dev/null || true   # nginx graceful shutdown
    kill -QUIT "$php_pid" 2>/dev/null || true      # php-fpm graceful shutdown
    wait "$nginx_pid" "$php_pid" 2>/dev/null || true
    exit 0
}
trap term TERM INT

php-fpm85 -F &
php_pid=$!

nginx -g 'daemon off;' &
nginx_pid=$!

# Block here; a trapped signal interrupts wait and runs term(). If a service
# exits on its own, wait returns and the container stops (the HEALTHCHECK would
# already have caught a dead service and let HAProxy fail over).
wait
