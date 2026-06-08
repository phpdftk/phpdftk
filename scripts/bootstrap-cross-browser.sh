#!/usr/bin/env bash
# Install everything the cross-browser PDF oracle needs.
#
# What this script does:
#   - Verifies host OS (Darwin or Linux); refuses anything else.
#   - Installs / verifies host packages (Homebrew on Darwin, apt on
#     Debian/Ubuntu Linux). The list is the same one the GHA
#     workflow at .github/workflows/cross-browser.yml installs.
#   - Runs `npm install` inside scripts/cross-browser/ to pull
#     Playwright + bundled Chromium.
#   - On Darwin, builds the Swift WebKit driver via build-webkit.sh.
#   - Probes for Firefox.app (Darwin) or a Linux Firefox build
#     (Docker handles the latter in normal use).
#
# What this script intentionally does NOT do:
#   - Install Docker. The render-docker.sh path needs Docker
#     Desktop on macOS and a working dockerd on Linux; print a
#     hint if it's missing.
#   - Touch /usr/local without permission. Falls back to ~/.local
#     for binaries when /usr/local isn't writable.
#
# Usage:
#   scripts/bootstrap-cross-browser.sh
#   scripts/bootstrap-cross-browser.sh --check    # verify, don't install
#
# References:
#   docs/plans/cross-browser-oracle.md
#   scripts/cross-browser/render.mjs
#   .github/workflows/cross-browser.yml
set -euo pipefail

CHECK_ONLY=0
if [[ "${1:-}" == "--check" ]]; then
    CHECK_ONLY=1
fi

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OS="$(uname -s)"

echo "phpdftk cross-browser bootstrap"
echo "  repo:  $REPO_ROOT"
echo "  os:    $OS"
echo "  mode:  $([[ $CHECK_ONLY -eq 1 ]] && echo 'check only' || echo 'install missing')"
echo

# ---------------------------------------------------------------------
# Host packages
# ---------------------------------------------------------------------
case "$OS" in
    Darwin)
        if ! command -v brew >/dev/null 2>&1; then
            echo "✗ Homebrew is required on macOS but not on PATH." >&2
            echo "  Install from https://brew.sh, then re-run." >&2
            exit 1
        fi
        # Each entry: "brew-formula:probe-command". The probe is what we
        # actually care about (an executable that responds to --version);
        # the brew name is only used if we have to install.
        HOST_PKGS=(
            "node@22:node"
            "imagemagick:magick"
            "ghostscript:gs"
            "geckodriver:geckodriver"
        )
        for entry in "${HOST_PKGS[@]}"; do
            pkg="${entry%%:*}"
            probe="${entry##*:}"
            if command -v "$probe" >/dev/null 2>&1; then
                ver=$("$probe" --version 2>&1 | head -1)
                echo "✓ $pkg ($ver)"
            elif [[ $CHECK_ONLY -eq 1 ]]; then
                echo "✗ $pkg (run without --check to install)"
            else
                echo "→ brew install $pkg"
                brew install "$pkg"
            fi
        done
        ;;
    Linux)
        if ! command -v apt-get >/dev/null 2>&1; then
            echo "✗ Only Debian/Ubuntu apt-based Linux is supported by this script." >&2
            echo "  Install Node 22, ImageMagick, ghostscript, geckodriver, and fonts-liberation manually." >&2
            exit 1
        fi
        APT_PKGS=(nodejs npm imagemagick ghostscript fonts-liberation fonts-dejavu-core fonts-noto-core)
        for pkg in "${APT_PKGS[@]}"; do
            if dpkg -s "$pkg" >/dev/null 2>&1; then
                echo "✓ $pkg"
            elif [[ $CHECK_ONLY -eq 1 ]]; then
                echo "✗ $pkg (run without --check to install)"
            else
                echo "→ apt-get install -y $pkg"
                sudo apt-get install -y --no-install-recommends "$pkg"
            fi
        done
        # geckodriver isn't in jammy apt; download the Mozilla release.
        GECKO_BIN="/usr/local/bin/geckodriver"
        if [[ -x "$GECKO_BIN" ]]; then
            echo "✓ geckodriver ($("$GECKO_BIN" --version | head -1))"
        elif [[ $CHECK_ONLY -eq 1 ]]; then
            echo "✗ geckodriver (run without --check to install)"
        else
            echo "→ download mozilla/geckodriver"
            ARCH=$(uname -m)
            case "$ARCH" in
                x86_64)  GECKO_OS=linux64 ;;
                aarch64) GECKO_OS=linux-aarch64 ;;
                *) echo "unsupported arch: $ARCH" >&2; exit 1 ;;
            esac
            GECKO_VER="${GECKO_VER:-v0.37.0}"
            curl -fsSL "https://github.com/mozilla/geckodriver/releases/download/${GECKO_VER}/geckodriver-${GECKO_VER}-${GECKO_OS}.tar.gz" \
                | sudo tar xz -C /usr/local/bin geckodriver
            sudo chmod +x "$GECKO_BIN"
        fi
        ;;
    *)
        echo "✗ Unsupported OS: $OS" >&2
        exit 1
        ;;
esac

# ---------------------------------------------------------------------
# Node deps for scripts/cross-browser/ (Playwright + bundled Chromium)
# ---------------------------------------------------------------------
echo
NODE_DIR="$REPO_ROOT/scripts/cross-browser"
if [[ -d "$NODE_DIR/node_modules" ]]; then
    echo "✓ scripts/cross-browser/node_modules (already installed)"
elif [[ $CHECK_ONLY -eq 1 ]]; then
    echo "✗ scripts/cross-browser/node_modules (run without --check to install)"
else
    echo "→ npm install --omit=dev in $NODE_DIR"
    (cd "$NODE_DIR" && npm install --no-audit --no-fund --omit=dev)
fi

# Playwright bundles its own browsers under ~/.cache/ms-playwright
# (or ~/Library/Caches/ms-playwright on macOS). We only need
# Chromium for the oracle's `page.pdf()` call. Probe directly for
# the cached binary rather than parsing `playwright install` output.
PW_CACHE_DARWIN="$HOME/Library/Caches/ms-playwright"
PW_CACHE_LINUX="$HOME/.cache/ms-playwright"
PW_INSTALLED=0
if [[ -d "$PW_CACHE_DARWIN" ]] && ls "$PW_CACHE_DARWIN"/chromium*/chrome-mac/Chromium.app >/dev/null 2>&1; then
    PW_INSTALLED=1
elif [[ -d "$PW_CACHE_LINUX" ]] && ls "$PW_CACHE_LINUX"/chromium*/chrome-linux/chrome >/dev/null 2>&1; then
    PW_INSTALLED=1
fi
if [[ -d "$NODE_DIR/node_modules" ]]; then
    if [[ $PW_INSTALLED -eq 1 ]]; then
        echo "✓ Playwright chromium (cached)"
    elif [[ $CHECK_ONLY -eq 1 ]]; then
        echo "✗ Playwright chromium (run without --check to install)"
    else
        echo "→ npx playwright install chromium"
        (cd "$NODE_DIR" && npx playwright install chromium)
    fi
fi

# ---------------------------------------------------------------------
# WebKit driver (macOS only — Swift WKWebView wrapper)
# ---------------------------------------------------------------------
if [[ "$OS" == "Darwin" ]]; then
    echo
    WEBKIT_BIN="${WEBKIT_CLI:-$HOME/.local/bin/webkit-render}"
    if [[ -x "$WEBKIT_BIN" ]]; then
        echo "✓ webkit-render at $WEBKIT_BIN"
    elif [[ $CHECK_ONLY -eq 1 ]]; then
        echo "✗ webkit-render (run without --check to build)"
    else
        echo "→ build webkit-render"
        "$REPO_ROOT/scripts/cross-browser/build-webkit.sh"
    fi
fi

# ---------------------------------------------------------------------
# Firefox.app (macOS) / Docker (Linux fallback)
# ---------------------------------------------------------------------
if [[ "$OS" == "Darwin" ]]; then
    if [[ -d /Applications/Firefox.app ]]; then
        echo "✓ Firefox.app at /Applications/Firefox.app"
    else
        echo "⚠ Firefox.app not found at /Applications/Firefox.app."
        echo "    Install from https://www.mozilla.org/firefox/ or set FIREFOX_CLI=path."
    fi
fi

if command -v docker >/dev/null 2>&1; then
    if docker info >/dev/null 2>&1; then
        echo "✓ Docker daemon reachable (used by render-docker.sh on Linux hosts)"
    else
        echo "⚠ docker installed but daemon isn't reachable; render-docker.sh will fail."
    fi
else
    if [[ "$OS" == "Linux" ]]; then
        echo "⚠ Docker not installed. render-docker.sh needs it for the Firefox fallback path."
    fi
fi

echo
echo "bootstrap complete."
echo "  Next: WEBKIT_CLI=$HOME/.local/bin/webkit-render \\"
echo "        packages/wpt-harness/bin/wpt cb-sweep --scope=rendering --skip-testharness --max=10"
