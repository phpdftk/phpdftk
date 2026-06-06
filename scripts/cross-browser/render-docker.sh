#!/usr/bin/env bash
# Wrapper: render a fixture through a browser engine inside the
# cross-browser Docker image. The host calls this when the bare-metal
# path can't run the requested engine (Firefox on macOS host, anything
# WebKit until Phase A3 lands a real binding).
#
# Usage:
#   ./render-docker.sh <engine> <absolute-fixture-path> <output-path>
#
# Example:
#   ./render-docker.sh firefox \
#     /tmp/wpt-sparse/css/css-backgrounds/background-image-001.html \
#     /tmp/firefox.pdf
#
# Builds the image on first invocation (cached afterwards).
#
# Reference: docs/plans/cross-browser-oracle.md § Phase A2

set -euo pipefail

if [[ $# -ne 3 ]]; then
    echo "usage: $0 <engine> <fixture-abs-path> <output-path>" >&2
    exit 1
fi

ENGINE="$1"
FIXTURE_HOST="$2"
OUTPUT_HOST="$3"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
IMAGE_TAG="${PHPDFTK_CROSS_BROWSER_TAG:-phpdftk/cross-browser:dev}"

if [[ ! -f "$FIXTURE_HOST" ]]; then
    echo "fixture not found: $FIXTURE_HOST" >&2
    exit 1
fi

# Build the image if it isn't cached. Use --quiet so we only see output
# when something goes wrong; first build is ~3 min, subsequent are seconds.
if ! docker image inspect "$IMAGE_TAG" >/dev/null 2>&1; then
    echo "building $IMAGE_TAG (first run only)…" >&2
    docker build --quiet -t "$IMAGE_TAG" "$SCRIPT_DIR" >&2
fi

# Stage the fixture's *directory* into the container so relative refs
# (../support/foo.png) resolve inside the sandbox. We mount the parent
# read-only to avoid surprises.
FIXTURE_DIR="$(cd "$(dirname "$FIXTURE_HOST")" && pwd)"
FIXTURE_BASE="$(basename "$FIXTURE_HOST")"
OUTPUT_DIR="$(cd "$(dirname "$OUTPUT_HOST")" && pwd)"
OUTPUT_BASE="$(basename "$OUTPUT_HOST")"

docker run --rm \
    --user "$(id -u):$(id -g)" \
    -v "$FIXTURE_DIR:/in:ro" \
    -v "$OUTPUT_DIR:/out" \
    "$IMAGE_TAG" \
    "$ENGINE" \
    "/in/$FIXTURE_BASE" \
    "--output=/out/$OUTPUT_BASE"
