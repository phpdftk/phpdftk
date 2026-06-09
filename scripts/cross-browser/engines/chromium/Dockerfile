# Persistent Chromium PDF render daemon for the cross-browser oracle.
#
# Build:
#   docker build -t phpdftk/chromium-daemon:dev \
#     scripts/cross-browser/engines/chromium/
#
# Run standalone (the canonical path is `docker compose up chromium`):
#   docker run --rm -p 127.0.0.1:9101:9101 \
#     -v $PWD/vendor-data/wpt:/wpt:ro \
#     -v $PWD/var/wpt/browser-cache:/var/cache/browser \
#     phpdftk/chromium-daemon:dev
#
# Reference: docs/plans/cross-browser-container-sweep.md § Phase P0
#
# The Playwright base image ships Node 22 + Playwright + Chromium +
# all the Linux system libs Chromium needs. We layer the daemon on
# top; no chromium download in the build itself.
FROM mcr.microsoft.com/playwright:v1.49.1-jammy

WORKDIR /opt/daemon

COPY package.json ./
RUN npm install --no-audit --no-fund --omit=dev

COPY server.mjs lib.mjs print-options.mjs ./

ENV PORT=9101 \
    WPT_ROOT=/wpt \
    CACHE_DIR=/var/cache/browser \
    NODE_ENV=production

EXPOSE 9101

# Pre-create the cache root so the first cache write doesn't race on
# mkdir when the volume mount comes up empty.
RUN mkdir -p /var/cache/browser/chromium

ENTRYPOINT ["node", "/opt/daemon/server.mjs"]
