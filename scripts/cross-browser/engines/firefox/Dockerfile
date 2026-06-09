# Persistent Firefox PDF render daemon for the cross-browser oracle.
#
# Build:
#   docker build -t phpdftk/firefox-daemon:dev \
#     scripts/cross-browser/engines/firefox/
#
# Reference: docs/plans/cross-browser-container-sweep.md § Phase P1
#
# Why the Playwright base image when we don't use Playwright? Because
# it already ships the long list of Linux system libs that Firefox
# needs to bring up its renderer (libnss3, libxkbcommon, libgbm,
# fonts-liberation, …). Pulling them in ourselves on a clean Debian
# image is dozens of explicit apt installs; the Playwright image
# bundles them, even though we drive Firefox over geckodriver/WebDriver
# rather than through the Playwright API.
#
# We DO NOT use Playwright's bundled Firefox: it's a patched build
# that strips `--print-to-pdf` and isn't reachable via geckodriver
# either. We install Mozilla's upstream release tarball instead, plus
# geckodriver from Mozilla's release page.
FROM mcr.microsoft.com/playwright:v1.49.1-jammy

ARG TARGETARCH
ARG GECKODRIVER_VERSION=v0.37.0

ENV FIREFOX_HOME=/opt/firefox \
    GECKODRIVER=/usr/local/bin/geckodriver \
    PORT=9102 \
    WPT_ROOT=/wpt \
    CACHE_DIR=/var/cache/browser \
    NODE_ENV=production

# 1. Install host packages the Firefox tarball relies on but the
#    Playwright base might not have. xvfb is included so the daemon
#    can fall back to a virtual display if the bundled Wayland/X
#    bring-up misbehaves — Firefox needs a display target even in
#    headless mode on Linux.
# 2. Drop Mozilla's release tarball at /opt/firefox/, link the binary.
# 3. Drop the geckodriver binary at /usr/local/bin/.
RUN set -eux; \
    apt-get update; \
    DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
        wget ca-certificates xz-utils libgtk-3-0 libdbus-glib-1-2 libasound2 \
        xvfb; \
    rm -rf /var/lib/apt/lists/*; \
    case "${TARGETARCH:-amd64}" in \
        amd64)  MOZ_OS=linux64;        GECKO_OS=linux64 ;; \
        arm64)  MOZ_OS=linux64-aarch64; GECKO_OS=linux-aarch64 ;; \
        *) echo "unsupported TARGETARCH=$TARGETARCH" >&2; exit 1 ;; \
    esac; \
    mkdir -p "$FIREFOX_HOME"; \
    wget --quiet -O /tmp/firefox.tar.xz \
        "https://download.mozilla.org/?product=firefox-latest-ssl&os=${MOZ_OS}&lang=en-US"; \
    tar -xJf /tmp/firefox.tar.xz -C "$FIREFOX_HOME" --strip-components=1; \
    rm /tmp/firefox.tar.xz; \
    ln -sf "$FIREFOX_HOME/firefox" /usr/local/bin/firefox-cli; \
    /usr/local/bin/firefox-cli --version; \
    wget --quiet -O /tmp/geckodriver.tar.gz \
        "https://github.com/mozilla/geckodriver/releases/download/${GECKODRIVER_VERSION}/geckodriver-${GECKODRIVER_VERSION}-${GECKO_OS}.tar.gz"; \
    tar -xzf /tmp/geckodriver.tar.gz -C /usr/local/bin geckodriver; \
    chmod +x /usr/local/bin/geckodriver; \
    rm /tmp/geckodriver.tar.gz; \
    geckodriver --version

WORKDIR /opt/daemon
COPY package.json ./
# No npm install needed — the daemon uses only node: built-ins.

COPY server.mjs lib.mjs print-options.mjs ./

EXPOSE 9102

RUN mkdir -p /var/cache/browser/firefox

ENV FIREFOX_BIN=/usr/local/bin/firefox-cli

ENTRYPOINT ["node", "/opt/daemon/server.mjs"]
