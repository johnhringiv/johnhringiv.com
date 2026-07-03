# Stage 1: Build
FROM node:alpine AS builder

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci --omit=dev
# Builder stays on PHP 8.3: the image pipeline (generate_images_build.php) needs the
# VIPS PHP extension, which Alpine only packages as php83-pecl-vips (no php85 build).
# This stage only generates static assets, so its PHP version is independent of what
# the runtime stage below serves the site with.
RUN apk --no-cache add php83 php83-pecl-vips php83-sqlite3 git bash \
    libheif libheif-dev aom-libs \
    chromium nss freetype harfbuzz ca-certificates \
    font-jetbrains-mono ttf-liberation \
    inkscape \
    && ln -s /usr/bin/php83 /usr/bin/php

# Configure Puppeteer to use system Chromium
ENV PUPPETEER_SKIP_CHROMIUM_DOWNLOAD=true
ENV PUPPETEER_EXECUTABLE_PATH=/usr/bin/chromium-browser

COPY .git /app/.git
COPY scripts /app/scripts/

COPY www/ /app/www/
COPY src /app/src/
COPY custom_icons /app/custom_icons/
COPY code_snippets /app/code_snippets/
COPY data/ /app/data/

# Generate assets
RUN php scripts/build_db.php
RUN bash scripts/generate_sprite.sh prod && php scripts/sitemap.php
RUN node scripts/generate_shiki_highlights.mjs
RUN node scripts/generate_mermaid_diagrams.js

# Pre-generate all responsive image sizes and OG images (includes SVG → PNG conversion)
RUN php scripts/generate_images_build.php

# Build CSS and JS bundles (minified)
RUN npm run build:prod

FROM alpine:latest

WORKDIR /var/www/html

# Runtime serves the site with PHP 8.5. OPcache is built into the php85 base package
# (no separate php85-opcache); config/php.ini only tunes it.
RUN apk upgrade --no-cache && apk --no-cache add php85 php85-fpm php85-sqlite3 nginx tini

# Configure nginx
COPY config/nginx.conf /etc/nginx/nginx.conf

# Configure PHP-FPM
COPY config/fpm-pool.conf /etc/php85/php-fpm.d/www.conf
COPY config/php.ini /etc/php85/conf.d/custom.ini

COPY config/start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

# Make sure files/folders needed by the processes are accessable when they run under the nobody user
RUN chown -R nobody:nobody /var/www/html /run /var/lib/nginx /var/log/nginx

# Switch to use a non-root user from here on
USER nobody

# Add application
COPY --from=builder --chown=nobody app/www /var/www/html/


# Expose the port nginx is reachable on
EXPOSE 8080

# Use tini for proper signal handling
ENTRYPOINT ["/sbin/tini", "--"]
CMD ["/usr/local/bin/start.sh"]

HEALTHCHECK --interval=30s --timeout=10s --start-period=10s CMD pidof php-fpm85 && pidof nginx
