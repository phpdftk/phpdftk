# Persistent WebKit (real WebKit, NOT Blink) PDF render daemon for
# the cross-browser oracle. Linux-only — there is no Apple WebKit on
# Linux without webkit2gtk, and Playwright's bundled webkit binary
# is Chromium-only for PDFs.
#
# Build:
#   docker build -t phpdftk/webkit-daemon:dev \
#     scripts/cross-browser/engines/webkit/
#
# Reference: docs/plans/cross-browser-container-sweep.md § Phase P2
#
# Why debian:trixie? Igalia (who maintain webkit2gtk in-tree with
# Apple's WebKit) ships current WebKit through Debian/Ubuntu apt;
# trixie's libwebkit2gtk-4.1-0 tracks WebKit ~2.46+. That's a real,
# upstream-current WebKit — not a Chromium fork — which is the whole
# reason WebKit is in the oracle.
#
# Why xvfb? WebKitWebView needs a display target even in "headless"
# mode (Gtk widgets always require a connection to a display server).
# Xvfb-run wraps the daemon's main entrypoint so WebKit gets a
# virtual display target without us shipping a real one.
FROM debian:trixie-slim

ARG TARGETARCH

ENV PORT=9103 \
    WPT_ROOT=/wpt \
    CACHE_DIR=/var/cache/browser \
    DEBIAN_FRONTEND=noninteractive \
    PYTHONUNBUFFERED=1 \
    # Restrict GTK's print backend to the file writer. Without this,
    # the default backend tries to enumerate CUPS printers and fails
    # with "Printer not found" in containers that don't run cupsd
    # (which is all of them). The file backend is what we actually
    # want — it honours `output-uri` and writes the PDF directly.
    GTK_PRINT_BACKENDS=file

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        ca-certificates \
        libwebkit2gtk-4.1-0 \
        gir1.2-webkit2-4.1 \
        gir1.2-gtk-3.0 \
        python3 \
        python3-gi \
        xvfb \
        xauth \
        fonts-liberation \
        fonts-dejavu-core \
        fonts-noto-core \
        wget; \
    rm -rf /var/lib/apt/lists/*

WORKDIR /opt/daemon

COPY server.py lib.py print_options.py entrypoint.sh ./
RUN chmod +x entrypoint.sh

EXPOSE 9103

RUN mkdir -p /var/cache/browser/webkit

# Xvfb-run owns the lifecycle: it brings up the virtual X server,
# exports DISPLAY, and execs the daemon under it. -a auto-picks a
# server number so we don't collide if the container is restarted
# rapidly; --server-args sets a reasonable virtual resolution.
ENTRYPOINT ["./entrypoint.sh"]
