#!/usr/bin/env bash
# End-to-end integration test for the firefox daemon.
#
# Runs the actual daemon (in Docker via compose, or directly via
# `node server.mjs` if geckodriver + firefox-cli are reachable on
# PATH) and hits the protocol surface with real fixtures. Verifies:
#
#   1. /status returns engine=firefox after the eager session is up
#   2. /render produces a real PDF (starts with %PDF-)
#   3. Cache hit on second render returns from_cache: true
#   4. /render rejects fixtures outside the WPT mount
#
# Skipped automatically when neither Docker nor a local
# (firefox-cli + geckodriver) is available. Not part of the standard
# PHPUnit suite — heavy + browser-dependent — but wired into CI
# behind the cross-browser job.
#
# Reference: docs/plans/cross-browser-container-sweep.md § Phase P1
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../../../.." && pwd)"
ENGINE_DIR="$REPO_ROOT/scripts/cross-browser/engines/firefox"
PORT="${FIREFOX_DAEMON_PORT:-9102}"

fail() { echo "✗ $1" >&2; exit 1; }
pass() { echo "✓ $1"; }

# ---------------------------------------------------------------------
# Determine launch mode
# ---------------------------------------------------------------------
MODE=""
if command -v docker >/dev/null 2>&1 && docker info >/dev/null 2>&1; then
    MODE="compose"
elif command -v node >/dev/null 2>&1 \
        && command -v geckodriver >/dev/null 2>&1; then
    # Pick a Firefox binary — env var wins, else fall back to the
    # macOS app bundle, else PATH.
    if [[ -n "${FIREFOX_BIN:-}" ]] && [[ -x "$FIREFOX_BIN" ]]; then
        : # honour env
    elif [[ -x "/Applications/Firefox.app/Contents/MacOS/firefox" ]]; then
        export FIREFOX_BIN="/Applications/Firefox.app/Contents/MacOS/firefox"
    elif command -v firefox-cli >/dev/null 2>&1; then
        export FIREFOX_BIN=$(command -v firefox-cli)
    elif command -v firefox >/dev/null 2>&1; then
        export FIREFOX_BIN=$(command -v firefox)
    else
        echo "↷ skipping: needs Docker, OR (node + geckodriver + a Firefox binary)" >&2
        exit 0
    fi
    MODE="node"
else
    echo "↷ skipping: needs Docker, OR (node + geckodriver + a Firefox binary)" >&2
    exit 0
fi
echo "Launch mode: $MODE (firefox=${FIREFOX_BIN:-bundled})"

# ---------------------------------------------------------------------
# Bring up the daemon
# ---------------------------------------------------------------------
DAEMON_PID=""
cleanup() {
    if [[ "$MODE" == "compose" ]]; then
        (cd "$REPO_ROOT" && docker compose down firefox 2>/dev/null || true)
    elif [[ -n "$DAEMON_PID" ]]; then
        kill -TERM "$DAEMON_PID" 2>/dev/null || true
        wait "$DAEMON_PID" 2>/dev/null || true
    fi
}
trap cleanup EXIT

if [[ "$MODE" == "compose" ]]; then
    (cd "$REPO_ROOT" && docker compose up -d --wait firefox)
else
    WPT_ROOT="$REPO_ROOT/packages/wpt-harness/curated/smoke" \
        CACHE_DIR="$REPO_ROOT/var/wpt/browser-cache" \
        PORT="$PORT" \
        GECKODRIVER_BIN="$(command -v geckodriver)" \
        node "$ENGINE_DIR/server.mjs" >/tmp/firefox-daemon.log 2>&1 &
    DAEMON_PID=$!
    # Geckodriver + Firefox bring-up is slower than Chromium (~3-5s
    # cold). Wait up to 60s for /status to reach ready: true rather
    # than just "respond" — Firefox is the engine where this matters
    # most because session creation is the slow step.
    for _ in $(seq 1 120); do
        if curl -sf "http://127.0.0.1:$PORT/status" 2>/dev/null \
                | grep -q '"ready":true'; then
            break
        fi
        sleep 0.5
    done
fi

# ---------------------------------------------------------------------
# 1. /status returns engine=firefox
# ---------------------------------------------------------------------
STATUS=$(curl -sf "http://127.0.0.1:$PORT/status") \
    || fail "/status didn't respond"
if ! echo "$STATUS" | grep -q '"engine":"firefox"'; then
    fail "/status missing engine: $STATUS"
fi
if ! echo "$STATUS" | grep -q '"ready":true'; then
    fail "/status never reached ready: true (firefox session bring-up failed?): $STATUS$(tail -20 /tmp/firefox-daemon.log 2>/dev/null)"
fi
pass "/status responds with engine=firefox, ready=true"

# ---------------------------------------------------------------------
# 2. /render produces a real PDF for a smoke fixture
# ---------------------------------------------------------------------
SMOKE_DIR="$REPO_ROOT/packages/wpt-harness/curated/smoke"
SMOKE_FIXTURE=$(ls "$SMOKE_DIR"/*.html 2>/dev/null | head -1)
if [[ -z "$SMOKE_FIXTURE" ]]; then
    fail "no smoke fixtures found under $SMOKE_DIR"
fi
if [[ "$MODE" == "compose" ]]; then
    DAEMON_PATH="/wpt/css/css-display/display-001.html"
    if ! docker compose exec -T firefox test -f "$DAEMON_PATH"; then
        echo "↷ skipping render check (curated smoke not in /wpt mount); use node mode for full coverage"
        exit 0
    fi
else
    DAEMON_PATH="$SMOKE_FIXTURE"
fi

PAYLOAD=$(printf '{"fixture":"%s","cache_key":"%s","timeout_ms":30000}' \
    "$DAEMON_PATH" "$(printf '%064d' 0)")
RESP=$(curl -sf -X POST "http://127.0.0.1:$PORT/render" \
    -H 'content-type: application/json' \
    -d "$PAYLOAD") || fail "/render failed: $(tail -20 /tmp/firefox-daemon.log 2>/dev/null)"
B64=$(echo "$RESP" | node -e \
    'let d=""; process.stdin.on("data",c=>d+=c).on("end",()=>{
        try { process.stdout.write(JSON.parse(d).pdf_bytes_base64 ?? ""); }
        catch { process.exit(1); }
    });')
if [[ -z "$B64" ]]; then
    fail "no pdf_bytes_base64 in render response: $RESP"
fi
echo "$B64" | base64 -d > /tmp/firefox-render-test.pdf
HEAD=$(head -c 5 /tmp/firefox-render-test.pdf)
[[ "$HEAD" == "%PDF-" ]] || fail "render output isn't a PDF (got: $HEAD)"
pass "/render produces a valid PDF for a smoke fixture"

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
echo "✓ firefox daemon integration smoke passed"
