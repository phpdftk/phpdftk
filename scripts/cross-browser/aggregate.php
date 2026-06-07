<?php

declare(strict_types=1);

/**
 * Cross-browser oracle shard aggregator.
 *
 * Reads one or more per-shard JSON dumps produced by
 * `wpt cross-browser --json=<path>` and emits a single Markdown report
 * to stdout. Designed to drop into a GHA workflow:
 *
 *   php scripts/cross-browser/aggregate.php \
 *       artifacts/shard-N/result.json artifacts/shard-M/result.json ... \
 *       --commit-sha=$GITHUB_SHA \
 *       --pr-number=$PR_NUMBER \
 *       --repo=$GITHUB_REPOSITORY \
 *       --top-fails=10 \
 *       > comment.md
 *
 * Per-shard JSON schema (see packages/wpt-harness/bin/wpt):
 *
 *   {
 *     "shard": { "index": <int>, "count": <int> },
 *     "engines_available": { "chromium": <bool>, "firefox": <bool>, "webkit": <bool> },
 *     "tallies": { "pass": <int>, "fail": <int>, "skip_disagree": <int>,
 *                  "insufficient_engines": <int>, "harness_error": <int> },
 *     "results": [
 *       {
 *         "testId": "...",
 *         "verdict": "pass" | "fail" | "skip_disagree" | "insufficient_engines" | "harness_error",
 *         "reason": "...",
 *         "consensus": [ "chromium", "webkit", ... ],
 *         "ours": { "chromium": <float AE>, ... },
 *         "pairs": { "chromium": { "webkit": <float AE>, ... }, ... },
 *         "engineMissing": [ ... ],
 *         "renderMicros": <float>,
 *         "fuzzBudget": <float>
 *       }, ...
 *     ]
 *   }
 *
 * The aggregator does not (yet) compare against a baseline; the cross-
 * browser oracle isn't a merge gate, so drift is informational. When
 * baselining lands, the aggregator gains a `--baseline=` flag and
 * computes per-test verdict deltas.
 */

const VERDICT_ORDER = ['pass', 'fail', 'skip_disagree', 'insufficient_engines', 'harness_error'];

/**
 * @param list<string> $argv
 *
 * @return array{
 *   shards: list<array<string, mixed>>,
 *   commitSha: ?string,
 *   prNumber: ?string,
 *   repo: ?string,
 *   topFails: int,
 *   verdictOut: ?string,
 * }
 */
function parseArgs(array $argv): array
{
    $shardFiles = [];
    $commitSha = null;
    $prNumber = null;
    $repo = null;
    $topFails = 10;
    $verdictOut = null;
    foreach (array_slice($argv, 1) as $arg) {
        if (str_starts_with($arg, '--commit-sha=')) {
            $commitSha = substr($arg, strlen('--commit-sha='));
        } elseif (str_starts_with($arg, '--pr-number=')) {
            $prNumber = substr($arg, strlen('--pr-number='));
        } elseif (str_starts_with($arg, '--repo=')) {
            $repo = substr($arg, strlen('--repo='));
        } elseif (str_starts_with($arg, '--top-fails=')) {
            $topFails = (int) substr($arg, strlen('--top-fails='));
        } elseif (str_starts_with($arg, '--verdict-output=')) {
            $verdictOut = substr($arg, strlen('--verdict-output='));
        } elseif (str_starts_with($arg, '--')) {
            fwrite(STDERR, "aggregate.php: unknown option: $arg\n");
            exit(2);
        } else {
            $shardFiles[] = $arg;
        }
    }
    $shards = [];
    foreach ($shardFiles as $f) {
        // GHA's `actions/download-artifact` puts each artefact in its
        // own subdir; the workflow passes a glob, the shell expands
        // it, and we accept both individual paths and directories
        // (in which case we read the first `result.json` inside).
        if (is_dir($f)) {
            $candidate = $f . '/result.json';
            if (!is_file($candidate)) {
                fwrite(STDERR, "aggregate.php: $f has no result.json\n");
                continue;
            }
            $f = $candidate;
        }
        if (!is_file($f)) {
            fwrite(STDERR, "aggregate.php: missing shard file: $f\n");
            continue;
        }
        $decoded = json_decode((string) file_get_contents($f), associative: true);
        if (!is_array($decoded)) {
            fwrite(STDERR, "aggregate.php: malformed shard: $f\n");
            continue;
        }
        $shards[] = $decoded;
    }
    return [
        'shards' => $shards,
        'commitSha' => $commitSha,
        'prNumber' => $prNumber,
        'repo' => $repo,
        'topFails' => $topFails,
        'verdictOut' => $verdictOut,
    ];
}

/**
 * Fold per-shard tally arrays into one. Tallies are simple sums.
 *
 * @param list<array<string, mixed>> $shards
 * @return array<string, int>
 */
function totalTallies(array $shards): array
{
    $totals = array_fill_keys(VERDICT_ORDER, 0);
    foreach ($shards as $shard) {
        foreach (VERDICT_ORDER as $key) {
            $totals[$key] += (int) ($shard['tallies'][$key] ?? 0);
        }
    }
    return $totals;
}

/**
 * Engine availability across the matrix: an engine counts as
 * "available somewhere" if any shard reports it true. We surface the
 * shard count alongside so the reader can spot "Firefox vanished from
 * 3/4 runners" situations.
 *
 * @param list<array<string, mixed>> $shards
 * @return array<string, array{available: int, total: int}>
 */
function engineAvailability(array $shards): array
{
    $totals = [];
    foreach (['chromium', 'firefox', 'webkit'] as $engine) {
        $available = 0;
        foreach ($shards as $shard) {
            if (!empty($shard['engines_available'][$engine])) {
                $available++;
            }
        }
        $totals[$engine] = ['available' => $available, 'total' => count($shards)];
    }
    return $totals;
}

/**
 * Flatten every shard's results into one list, sort by worst-of-ours
 * AE descending, return the top K failures. Skip-disagrees and
 * passes are excluded — the reader cares about fails first.
 *
 * @param list<array<string, mixed>> $shards
 * @return list<array<string, mixed>>
 */
function topFailures(array $shards, int $k): array
{
    $all = [];
    foreach ($shards as $shard) {
        foreach (($shard['results'] ?? []) as $r) {
            if ($r['verdict'] !== 'fail') {
                continue;
            }
            $worst = 0.0;
            foreach (($r['ours'] ?? []) as $score) {
                if ($score > $worst) {
                    $worst = (float) $score;
                }
            }
            $r['_worstAE'] = $worst;
            $all[] = $r;
        }
    }
    usort($all, static fn($a, $b) => $b['_worstAE'] <=> $a['_worstAE']);
    return array_slice($all, 0, $k);
}

/**
 * @param list<array<string, mixed>>             $shards
 * @param array<string, int>                     $tallies
 * @param array<string, array{available: int, total: int}> $availability
 * @param list<array<string, mixed>>             $topFails
 */
function renderMarkdown(
    array $shards,
    array $tallies,
    array $availability,
    array $topFails,
    ?string $commitSha,
    ?string $prNumber,
    ?string $repo,
): string {
    $lines = [];
    $lines[] = '<!-- cross-browser-oracle -->';
    $lines[] = '## Cross-browser PDF oracle';
    $lines[] = '';
    $lines[] = sprintf(
        '%d shards · %d engines (chromium/firefox/webkit)',
        count($shards),
        3,
    );
    if ($commitSha !== null && $repo !== null) {
        $lines[] = sprintf(
            'Commit: [`%s`](https://github.com/%s/commit/%s)',
            substr($commitSha, 0, 7),
            $repo,
            $commitSha,
        );
    }
    $lines[] = '';

    $total = array_sum($tallies);
    $lines[] = '### Results';
    $lines[] = '';
    $lines[] = '| Verdict | Count | Share |';
    $lines[] = '|---|---:|---:|';
    foreach (VERDICT_ORDER as $verdict) {
        $count = $tallies[$verdict];
        $pct = $total > 0 ? sprintf('%.1f%%', $count / $total * 100.0) : '—';
        $lines[] = sprintf('| %s | %d | %s |', $verdict, $count, $pct);
    }
    $lines[] = sprintf('| **total** | **%d** | |', $total);
    $lines[] = '';

    $lines[] = '### Engine availability';
    $lines[] = '';
    $lines[] = '| Engine | Available on |';
    $lines[] = '|---|---|';
    foreach ($availability as $engine => $stats) {
        $lines[] = sprintf(
            '| %s | %d / %d shards |',
            $engine,
            $stats['available'],
            $stats['total'],
        );
    }
    $lines[] = '';

    if ($topFails !== []) {
        $lines[] = sprintf('### Top %d regressions', count($topFails));
        $lines[] = '';
        $lines[] = '| Test | Worst AE | Consensus | Reason |';
        $lines[] = '|---|---:|---|---|';
        foreach ($topFails as $r) {
            $lines[] = sprintf(
                '| `%s` | %.2f%% | %s | %s |',
                $r['testId'],
                ($r['_worstAE'] ?? 0.0) * 100.0,
                implode(' + ', $r['consensus'] ?? []),
                str_replace('|', '\\|', (string) ($r['reason'] ?? '')),
            );
        }
        $lines[] = '';
    } elseif ($tallies['fail'] === 0) {
        $lines[] = '✅ No regressions detected.';
        $lines[] = '';
    }

    $lines[] = '<sub>The cross-browser oracle is informational; it is not a merge gate. See `docs/plans/cross-browser-oracle.md`.</sub>';
    $lines[] = '';
    return implode("\n", $lines);
}

$opts = parseArgs($argv);
if ($opts['shards'] === []) {
    fwrite(STDERR, "aggregate.php: no shard inputs supplied\n");
    exit(2);
}

$tallies = totalTallies($opts['shards']);
$availability = engineAvailability($opts['shards']);
$topFails = topFailures($opts['shards'], $opts['topFails']);

echo renderMarkdown(
    $opts['shards'],
    $tallies,
    $availability,
    $topFails,
    $opts['commitSha'],
    $opts['prNumber'],
    $opts['repo'],
);

if ($opts['verdictOut'] !== null) {
    file_put_contents($opts['verdictOut'], (string) json_encode([
        'tallies' => $tallies,
        'engines_available' => $availability,
        'fail_count' => $tallies['fail'],
        // Informational only — the workflow does not block on this.
        'overall_failed' => false,
    ], JSON_PRETTY_PRINT));
}
