#!/bin/sh

# Gracefully stop both services on termination
trap 'kill -TERM $(jobs -p); wait' TERM INT

# Start PHP-FPM in background
php-fpm85 -F &

# Start nginx in foreground
nginx -g 'daemon off;'

# Wait for both
wait