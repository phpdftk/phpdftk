#!/usr/bin/env bash
# Build the WebKit driver. macOS-only — Linux runners skip this and the
# ConsensusScorer falls back to two-of-two on Chromium + Gecko.
#
# Reference: docs/plans/cross-browser-oracle.md § Phase A3

set -euo pipefail

if [[ "$(uname)" != "Darwin" ]]; then
    echo "build-webkit.sh: webkit driver is macOS-only (this host is $(uname)); skipping." >&2
    exit 0
fi

if ! command -v swiftc >/dev/null 2>&1; then
    echo "build-webkit.sh: swiftc not on PATH. Install Xcode command-line tools (\`xcode-select --install\`)." >&2
    exit 1
fi

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
SRC="$SCRIPT_DIR/webkit-render.swift"
OUT="${WEBKIT_CLI:-/usr/local/bin/webkit-render}"

# Install to a user-writable path when /usr/local/bin isn't writable.
if [[ ! -w "$(dirname "$OUT")" ]]; then
    if [[ -n "${WEBKIT_CLI:-}" ]]; then
        echo "build-webkit.sh: WEBKIT_CLI=$WEBKIT_CLI directory not writable." >&2
        exit 1
    fi
    OUT="$HOME/.local/bin/webkit-render"
    mkdir -p "$(dirname "$OUT")"
    echo "build-webkit.sh: /usr/local/bin not writable; installing to $OUT instead." >&2
    echo "build-webkit.sh: set WEBKIT_CLI=$OUT in your shell to make render.mjs find it." >&2
fi

echo "swiftc -O $SRC -o $OUT" >&2
swiftc -O "$SRC" -o "$OUT"
"$OUT" --help 2>&1 | head -1 || true  # warm-up; binary may print usage
echo "build-webkit.sh: built $OUT" >&2
