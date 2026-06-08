#!/usr/bin/env bash
# Run `wpt cb-sweep` in N parallel shards against the entire WPT
# corpus and time the whole thing. Each shard writes its own JSON
# dump so we can aggregate after. The CRC32-based shard predicate
# in `cb-sweep` is deterministic on path, so re-runs across the
# parallel pool never shuffle which shard owns which fixture and
# the browser-cache hit rate stays high.
#
# Usage: cb-sweep-parallel.sh <shard-count> [--include=<glob>] [extra-args...]
set -euo pipefail

SHARD_COUNT="${1:-4}"
shift || true
EXTRA_ARGS=("$@")

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
WPT_ROOT="$REPO_ROOT/vendor-data/wpt"
BIN="$REPO_ROOT/packages/wpt-harness/bin/wpt"
OUT_DIR="/tmp/cb-sweep-parallel-$(date +%Y%m%d-%H%M%S)"
mkdir -p "$OUT_DIR"

echo "cb-sweep parallel run"
echo "  shards:   $SHARD_COUNT"
echo "  corpus:   $WPT_ROOT"
echo "  outputs:  $OUT_DIR"
echo "  extra:    ${EXTRA_ARGS[*]:-(none)}"
echo

START_TS=$(date +%s)

# Each shard renders thousands of fixtures in one process. The
# default PHP memory limit (128 MB) runs out around 3–4k fixtures —
# the Renderer / PdfWriter / Cascade aren't strictly leak-free across
# calls. Bump to 2 GB so a full-corpus shard makes it to the end.
# Override via $CB_SWEEP_MEMORY_LIMIT if you want headroom for a
# specific scope.
PHP_BIN="${PHP_BIN:-php}"
MEM_LIMIT="${CB_SWEEP_MEMORY_LIMIT:-2G}"

PIDS=()
for k in $(seq 1 "$SHARD_COUNT"); do
    LOG="$OUT_DIR/shard-$k.log"
    JSON="$OUT_DIR/shard-$k.json"
    (
        WEBKIT_CLI="${WEBKIT_CLI:-/Users/troymccabe/.local/bin/webkit-render}" \
            "$PHP_BIN" -d "memory_limit=$MEM_LIMIT" "$BIN" cb-sweep \
                --shard="$k/$SHARD_COUNT" \
                --json="$JSON" \
                "${EXTRA_ARGS[@]}" \
                >"$LOG" 2>&1
        echo "$? $k" >"$OUT_DIR/shard-$k.exit"
    ) &
    PID=$!
    PIDS+=("$PID")
    echo "  spawned shard $k/$SHARD_COUNT (pid $PID)"
done

echo
echo "Waiting on ${#PIDS[@]} workers…"
for p in "${PIDS[@]}"; do
    wait "$p" || true
done
END_TS=$(date +%s)
ELAPSED=$((END_TS - START_TS))

for k in $(seq 1 "$SHARD_COUNT"); do
    JSON="$OUT_DIR/shard-$k.json"
    if [[ -f "$JSON" ]]; then
        php -r "
            \$j = json_decode(file_get_contents('$JSON'), true);
            printf(\"  shard %d: %5d fixtures, %3d ours-errors\n\",
                $k, \$j['fixtures'] ?? 0, \$j['ours_errors'] ?? 0);
        "
    fi
done

ELAPSED_FMT=$(printf '%dh%02dm%02ds' "$((ELAPSED/3600))" "$((ELAPSED%3600/60))" "$((ELAPSED%60))")
echo
echo "All shards complete in ${ELAPSED}s (${ELAPSED_FMT})"
echo
echo "Aggregating to $OUT_DIR/combined.json…"
php "$REPO_ROOT/scripts/cb-sweep-aggregate.php" "$OUT_DIR"/shard-*.json > "$OUT_DIR/combined.json"
php <<PHP
<?php
\$j = json_decode(file_get_contents('$OUT_DIR/combined.json'), true);
printf("  total fixtures: %d\n", \$j['overall']['count'] ?? 0);
foreach ((\$j['overall']['means'] ?? []) as \$engine => \$mean) {
    printf("  mean vs %-9s: %s\n", \$engine,
        \$mean === null ? '-' : sprintf('%.2f%%', \$mean * 100));
}
PHP
