# Cross-browser oracle runner.
#
# Microsoft's Playwright base image already ships:
#   - Node.js 22 LTS
#   - Playwright (and the bundled chromium / firefox / webkit binaries)
#   - All Linux system libs required for headless browsers (libnss3,
#     libxkbcommon, libxshmfence, libgbm, fonts, …)
#
# We copy in our render.mjs + print-options.mjs on top and treat the
# container as a one-shot CLI that writes the PDF to /work/out.pdf.
#
# Build:
#   docker build -t phpdftk/cross-browser:dev scripts/cross-browser/
#
# Run (one-shot):
#   docker run --rm \
#     -v /path/to/fixture.html:/in/fixture.html:ro \
#     -v $(pwd)/out:/out \
#     phpdftk/cross-browser:dev \
#     node render.mjs firefox /in/fixture.html --output=/out/firefox.pdf
#
# Reference: docs/plans/cross-browser-oracle.md § Phase A2
# Pin to linux/amd64 because Mozilla doesn't ship arm64 Linux Firefox
# binaries from download.mozilla.org. CI runners are x86_64; macOS
# arm64 hosts run this via Docker's Rosetta emulation.
FROM --platform=linux/amd64 mcr.microsoft.com/playwright:v1.49.1-jammy

# Playwright's bundled Firefox is a custom patched build that strips
# `--print-to-pdf` (the binary writes "unrecognized command line flag
# -print-to-pdf" to stderr). Playwright also doesn't expose
# `page.pdf()` for Firefox — that API is Chromium-only.
#
# So Firefox CLI must come from somewhere else. Ubuntu jammy's apt
# `firefox` package is a SNAP shim that can't run in Docker; we
# download Mozilla's official Linux tarball directly and use that.
ENV FIREFOX_CLI_DIR=/opt/firefox-cli
RUN set -eux; \
    apt-get update; \
    DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
        wget ca-certificates xz-utils libgtk-3-0 libdbus-glib-1-2 libasound2 \
        xvfb; \
    rm -rf /var/lib/apt/lists/*; \
    mkdir -p "$FIREFOX_CLI_DIR"; \
    cd /tmp; \
    wget --quiet -O firefox.tar.xz \
        "https://download.mozilla.org/?product=firefox-latest-ssl&os=linux64&lang=en-US"; \
    tar -xJf firefox.tar.xz -C "$FIREFOX_CLI_DIR" --strip-components=1; \
    rm firefox.tar.xz; \
    ln -sf "$FIREFOX_CLI_DIR/firefox" /usr/local/bin/firefox-cli; \
    /usr/local/bin/firefox-cli --version

WORKDIR /opt/cross-browser
COPY package.json ./
RUN npm install --no-audit --no-fund --omit=dev

COPY render.mjs print-options.mjs ./

ENTRYPOINT ["node", "/opt/cross-browser/render.mjs"]
