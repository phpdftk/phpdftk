#!/usr/bin/env bash
# Parallel sharded WPT scoreboard. Splits a bucket into many per-subdir
# shards and runs them concurrently (the harness is single-threaded and
# CPU-bound, so N shards ≈ N× speedup on a multi-core box). Sums the
# per-shard JSON pass counts into one total.
#
# Usage: scripts/wpt-shard.sh [bucket] [parallelism] [label]
#   bucket       top-level corpus dir under vendor-data/wpt (default: css)
#   parallelism  concurrent shards (default: 8)
#   label        tag for the /tmp output dir (default: run)
#
# Prints per-shard counts as they finish, then a TOTAL line:
#   TOTAL pass=<n> inScope=<n> shards=<n>
set -u

BUCKET="${1:-css}"
PAR="${2:-8}"
LABEL="${3:-run}"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
CORPUS="$ROOT/vendor-data/wpt/$BUCKET"
OUT="/tmp/wpt-shard-$LABEL"
rm -rf "$OUT"; mkdir -p "$OUT"

# Build the shard list: every immediate subdir of the bucket, but split
# the mega-dirs one level deeper so no single shard dominates wall time
# (the harness is single-threaded per shard, so the biggest shard bounds
# the whole run). CSS2 + the largest feature dirs each hold 1k-9k files.
MEGA=" CSS2 css-grid css-text css-ui css-writing-modes css-flexbox css-transforms css-backgrounds "
shards=()
for d in "$CORPUS"/*/; do
  name="${d%/}"; name="${name##*/}"
  if [ "$BUCKET" = "css" ] && [[ "$MEGA" == *" $name "* ]]; then
    for s in "$CORPUS/$name"/*/; do
      [ -d "$s" ] || continue
      sn="${s%/}"; sn="${sn##*/}"
      shards+=("$BUCKET/$name/$sn/**")
    done
    # Loose top-level files: `*` (single segment, no slash) captures
    # files directly under the mega-dir without overlapping the
    # subdir `/**` shards. Keeps coverage identical to `<mega>/**`.
    shards+=("$BUCKET/$name/*")
  else
    shards+=("$BUCKET/$name/**")
  fi
done

# Match the scoreboard's methodology: settler-OFF for deterministic,
# Playwright-free counts comparable to scripts/wpt-baseline.json.
export WPT_DISABLE_DOM_SETTLER=1
printf '%s\n' "${shards[@]}" | xargs -P "$PAR" -I {} bash -c '
  shard="$1"; out="$2"; root="$3"
  safe="$(printf "%s" "$shard" | tr "/*" "__")"
  WPT_DISABLE_DOM_SETTLER=1 php "$root/packages/wpt-harness/bin/wpt" run --filter="$shard" --json="$out/$safe.json" >/dev/null 2>&1
  # A shard that dies (OOM is the usual cause — `background-size` alone can
  # exceed 1G, and concurrent sweeps make it likelier) leaves no JSON. Do NOT
  # fall back to 0: a zero is indistinguishable from a shard that legitimately
  # scored nothing, so the TOTAL comes out silently short and reads as a
  # regression. Mark it FAILED so it is visible in the log, and let the
  # aggregator below count it separately.
  if [ -s "$out/$safe.json" ]; then
    pass=$(python3 -c "import json,sys; d=json.load(open(sys.argv[1])); print(d[\"pass\"])" "$out/$safe.json" 2>/dev/null || echo FAILED)
    ins=$(python3 -c "import json,sys; d=json.load(open(sys.argv[1])); print(d[\"inScopeTotal\"])" "$out/$safe.json" 2>/dev/null || echo FAILED)
  else
    pass=FAILED; ins=FAILED
    echo "$shard" >> "$out/.failed-shards"
  fi
  printf "%-45s pass=%s inScope=%s\n" "$shard" "$pass" "$ins"
' _ {} "$OUT" "$ROOT"

# Aggregate.
python3 - "$OUT" <<'PY'
import json, sys, glob, os
tp = ti = n = 0
for f in glob.glob(os.path.join(sys.argv[1], "*.json")):
    try:
        d = json.load(open(f))
        tp += d["pass"]; ti += d["inScopeTotal"]; n += 1
    except Exception:
        pass
failed = []
fp = os.path.join(sys.argv[1], ".failed-shards")
if os.path.exists(fp):
    failed = [l.strip() for l in open(fp) if l.strip()]
if failed:
    print(f"TOTAL pass={tp} inScope={ti} shards={n}  *** INCOMPLETE: {len(failed)} shard(s) produced no JSON ***")
    for f in failed:
        print(f"  FAILED SHARD: {f}")
    print("  This total is UNDERSTATED. Re-run the failed shard(s) before comparing.")
    sys.exit(3)
print(f"TOTAL pass={tp} inScope={ti} shards={n}")
PY
