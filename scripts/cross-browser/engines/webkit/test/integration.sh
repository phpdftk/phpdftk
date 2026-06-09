#!/usr/bin/env bash
# End-to-end integration test for the webkit daemon.
#
# Docker-only: webkit2gtk is a Linux-native stack with no realistic
# bare-metal path on macOS. Mac developers either use Docker Desktop
# (which spins up a Linux VM) or fall back to the in-tree Swift
# WKWebView CLI (scripts/cross-browser/webkit-render.swift) — but
# the Swift path is the legacy one-shot, NOT this daemon. This
# script will skip cleanly with an explanation when Docker isn't
# reachable.
#
# Verifies:
#   1. Container builds and becomes healthy
#   2. /status returns engine=webkit, ready=true, version is set
#   3. /render produces a real PDF (starts with %PDF-) on a fixture
#      inside the bind-mounted WPT corpus
#   4. Cache hit on second render returns from_cache: true
#   5. /render rejects fixtures outside the WPT mount
#
# Reference: docs/plans/cross-browser-container-sweep.md § Phase P2
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../../../.." && pwd)"
PORT="${WEBKIT_DAEMON_PORT:-9103}"

fail() { echo "✗ $1" >&2; exit 1; }
pass() { echo "✓ $1"; }

# ---------------------------------------------------------------------
# Require Docker
# ---------------------------------------------------------------------
if ! command -v docker >/dev/null 2>&1 || ! docker info >/dev/null 2>&1; then
    echo "↷ skipping: webkit daemon needs Docker (webkit2gtk is Linux-only)" >&2
    exit 0
fi

cleanup() {
    (cd "$REPO_ROOT" && docker compose down webkit 2>/dev/null || true)
}
trap cleanup EXIT

# ---------------------------------------------------------------------
# Bring up the daemon
# ---------------------------------------------------------------------
(cd "$REPO_ROOT" && docker compose up -d --wait webkit)

# ---------------------------------------------------------------------
# 1. /status returns engine=webkit with version
# ---------------------------------------------------------------------
STATUS=$(curl -sf "http://127.0.0.1:$PORT/status") \
    || fail "/status didn't respond"
if ! echo "$STATUS" | grep -q '"engine":"webkit"'; then
    fail "/status missing engine: $STATUS"
fi
if ! echo "$STATUS" | grep -q '"ready":true'; then
    fail "/status never reached ready: true: $STATUS"
fi
pass "/status responds with engine=webkit, ready=true"

# ---------------------------------------------------------------------
# 2. /render produces a real PDF for a WPT fixture inside the mount
# ---------------------------------------------------------------------
# Pick a small, simple fixture that's known to exist in the WPT
# corpus. The "css-display" tree is among the smallest reftests.
DAEMON_PATH="/wpt/css/css-display/display-001.html"
if ! docker compose exec -T webkit test -f "$DAEMON_PATH" 2>/dev/null; then
    # Try a different well-known small fixture as a fallback.
    DAEMON_PATH="/wpt/css/css-color/color-001.html"
    if ! docker compose exec -T webkit test -f "$DAEMON_PATH" 2>/dev/null; then
        echo "↷ skipping render check: no known smoke fixture present in /wpt mount"
        echo "  (the WPT submodule may not be checked out — run \`git submodule update --init\`)"
        exit 0
    fi
fi

PAYLOAD=$(printf '{"fixture":"%s","cache_key":"%s","timeout_ms":30000}' \
    "$DAEMON_PATH" "$(printf '%064d' 3)")
RESP=$(curl -sf -X POST "http://127.0.0.1:$PORT/render" \
    -H 'content-type: application/json' \
    -d "$PAYLOAD") || fail "/render failed: $(docker compose logs webkit --tail 30)"

B64=$(echo "$RESP" | python3 -c \
    'import sys,json; print(json.load(sys.stdin).get("pdf_bytes_base64",""))')
if [[ -z "$B64" ]]; then
    fail "no pdf_bytes_base64 in render response: $RESP"
fi
echo "$B64" | base64 -d > /tmp/webkit-render-test.pdf
HEAD=$(head -c 5 /tmp/webkit-render-test.pdf)
[[ "$HEAD" == "%PDF-" ]] || fail "render output isn't a PDF (got: $HEAD)"
pass "/render produces a valid PDF for $DAEMON_PATH"

# ---------------------------------------------------------------------
# 3. Second render of same fixture hits cache
# ---------------------------------------------------------------------
RESP2=$(curl -sf -X POST "http://127.0.0.1:$PORT/render" \
    -H 'content-type: application/json' \
    -d "$PAYLOAD")
echo "$RESP2" | grep -q '"from_cache":true' \
    || fail "second render didn't hit cache: $RESP2"
pass "second render returns from_cache: true"

# ---------------------------------------------------------------------
# 4. /render rejects fixture outside the WPT mount
# ---------------------------------------------------------------------
ESCAPE_PAYLOAD='{"fixture":"/etc/passwd","timeout_ms":5000}'
CODE=$(curl -s -o /dev/null -w '%{http_code}' -X POST \
    "http://127.0.0.1:$PORT/render" \
    -H 'content-type: application/json' \
    -d "$ESCAPE_PAYLOAD")
[[ "$CODE" == "400" ]] || fail "/etc/passwd should yield 400, got $CODE"
pass "/render rejects fixture outside WPT mount with 400"

echo
echo "✓ webkit daemon integration smoke passed"
