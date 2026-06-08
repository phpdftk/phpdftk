#!/usr/bin/env bash
# Live dashboard for a running cb-sweep-parallel.sh run.
#
# Picks the most recent /tmp/cb-sweep-parallel-* directory and
# re-renders every 5 seconds. Watches per-shard progress (current /
# total fixtures, elapsed seconds, OOM detection from log tail),
# the engine PDF cache size, and the live PHP worker count.
#
# Ctrl+C exits the dashboard. The sweep keeps running.
#
# Usage:
#   scripts/cb-sweep-dashboard.sh
#   scripts/cb-sweep-dashboard.sh /tmp/cb-sweep-parallel-20260608-…  # pin a run
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CACHE_ROOT="${CB_SWEEP_CACHE:-$REPO_ROOT/var/wpt/browser-cache}"
RUN_DIR="${1:-}"

if [[ -z "$RUN_DIR" ]]; then
    RUN_DIR=$(ls -dt /tmp/cb-sweep-parallel-* 2>/dev/null | head -1 || true)
    if [[ -z "$RUN_DIR" ]]; then
        echo "no cb-sweep-parallel-* directory found in /tmp; pass one explicitly." >&2
        exit 1
    fi
fi
if [[ ! -d "$RUN_DIR" ]]; then
    echo "run dir not found: $RUN_DIR" >&2
    exit 1
fi

# Render a unicode progress bar of `current / total` at `width` chars.
bar() {
    local cur=$1 total=$2 width=${3:-20}
    [[ $total -eq 0 ]] && total=1
    local filled=$((cur * width / total))
    local empty=$((width - filled))
    printf "%s%s" \
        "$(printf '%.0s▓' $(seq 1 $filled 2>/dev/null))" \
        "$(printf '%.0s░' $(seq 1 $empty 2>/dev/null))"
}

# Detect the shard count from JSON output (or fall back to log files).
shard_count() {
    local k=0
    for f in "$RUN_DIR"/shard-*.log; do
        [[ -f "$f" ]] && k=$((k + 1))
    done
    echo $k
}

NSHARDS=$(shard_count)
[[ $NSHARDS -eq 0 ]] && NSHARDS=8

while true; do
    clear
    echo "=== cb-sweep parallel dashboard ==="
    echo "  run dir: $RUN_DIR"
    echo "  now:     $(date '+%Y-%m-%d %H:%M:%S')"
    echo

    printf "%-8s %-22s %8s   %s\n" "shard" "progress" "elapsed" "state"
    echo "------------------------------------------------------------------------"
    for k in $(seq 1 "$NSHARDS"); do
        log="$RUN_DIR/shard-$k.log"
        json="$RUN_DIR/shard-$k.json"
        [[ -f "$log" ]] || continue

        # Most recent progress line: `  ... 1234/4567   89s elapsed`
        # — the leading `...` may sit alone or stuck to the count
        # depending on the printf padding, so parse with a regex
        # rather than positional awk.
        last=$(grep -E "^\s+\.\.\." "$log" 2>/dev/null | tail -1)
        if [[ "$last" =~ ([0-9]+)/([0-9]+)[[:space:]]+([0-9]+)s ]]; then
            cur="${BASH_REMATCH[1]}"
            total="${BASH_REMATCH[2]}"
            elapsed="${BASH_REMATCH[3]}"
        else
            cur=0
            total=0
            elapsed=0
        fi

        state="run"
        if [[ -f "$json" ]] && command -v jq >/dev/null 2>&1 \
            && jq -e '.fixtures > 0' "$json" >/dev/null 2>&1; then
            state="done"
        elif tail -3 "$log" 2>/dev/null | grep -q "Fatal error"; then
            state="OOM!"
        elif ! pgrep -f "shard=$k/$NSHARDS" >/dev/null 2>&1; then
            state="?"
        fi

        pct=0
        [[ $total -gt 0 ]] && pct=$((cur * 100 / total))

        printf "%-8s %s %4d/%-4d %3d%%  %5ds   %s\n" \
            "shard-$k" \
            "$(bar "$cur" "$total" 12)" \
            "$cur" "$total" "$pct" \
            "$elapsed" \
            "$state"
    done

    echo
    echo "browser cache:"
    for e in chromium firefox webkit; do
        d="$CACHE_ROOT/$e"
        if [[ -d "$d" ]]; then
            count=$(find "$d" -name "*.pdf" 2>/dev/null | wc -l | tr -d ' ')
            size=$(du -sh "$d" 2>/dev/null | awk '{print $1}')
            printf "  %-9s %6d PDFs (%s)\n" "$e" "$count" "$size"
        fi
    done

    echo
    workers=$(pgrep -f "wpt cb-sweep --shard" 2>/dev/null | wc -l | tr -d ' ')
    echo "live workers: $workers / $NSHARDS"

    sleep 5
done
