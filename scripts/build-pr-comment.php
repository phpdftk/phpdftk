<?php

declare(strict_types=1);

/**
 * Build the unified PR-comment body that aggregates code-coverage, benchmark,
 * and compliance results for a pull request, with deltas computed against the
 * latest `main` baselines fetched from the `_coverage` / `_benchmarks` /
 * `_compliance` orphan branches.
 *
 * Usage:
 *   php scripts/build-pr-comment.php \
 *       --coverage-current=PATH    --coverage-baseline=PATH \
 *       --benchmarks-current=PATH  --benchmarks-baseline=PATH \
 *       --compliance-current=PATH  --compliance-baseline=PATH \
 *       --commit-sha=SHA \
 *       --pr-number=N \
 *       --repo=owner/repo \
 *       [--coverage-fail-threshold=1.0]    \
 *       [--benchmark-fail-threshold=15.0]  \
 *       [--verdict-output=PATH]
 *
 * Missing baselines are tolerated; deltas render as "—" with a footer note.
 * Output is markdown to stdout. The script always exits 0; CI is expected to
 * read the verdict JSON (--verdict-output) to decide whether to fail the
 * check, so the comment posts even on gate breach.
 */

const BENCH_HIGHLIGHT_THRESHOLD_PCT = 10.0;
const COMMENT_MARKER = '<!-- pr-report -->';

$opts = [];
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--([a-z-]+)=(.*)$/', $arg, $m)) {
        $opts[$m[1]] = $m[2];
    }
}

$commitSha = $opts['commit-sha'] ?? '';
$shortSha  = substr($commitSha, 0, 7) ?: '?';
$prNumber  = $opts['pr-number'] ?? '';
$repo      = $opts['repo'] ?? '';

$coverageFailPp     = (float) ($opts['coverage-fail-threshold']  ?? '1.0');
$benchmarkFailPct   = (float) ($opts['benchmark-fail-threshold'] ?? '15.0');
$verdictOutputPath  = $opts['verdict-output'] ?? '';

$coverageCurrent     = loadJson($opts['coverage-current']     ?? '');
$coverageBaseline    = loadJson($opts['coverage-baseline']    ?? '');
$benchmarksCurrent   = loadJson($opts['benchmarks-current']   ?? '');
$benchmarksBaseline  = loadJson($opts['benchmarks-baseline']  ?? '');
$complianceCurrent   = loadJson($opts['compliance-current']   ?? '');
$complianceBaseline  = loadJson($opts['compliance-baseline']  ?? '');

$missingBaselines = array_filter([
    'coverage'   => $coverageBaseline   === null,
    'benchmarks' => $benchmarksBaseline === null,
    'compliance' => $complianceBaseline === null,
]);

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function loadJson(string $path): ?array
{
    if ($path === '' || !is_file($path)) {
        return null;
    }
    $raw = file_get_contents($path);
    if ($raw === false || trim($raw) === '') {
        return null;
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : null;
}

/** Parse a phpbench-formatted duration string (e.g. "1.234ms") into microseconds. */
function parseDurationToUs(?string $value): ?float
{
    if ($value === null || $value === '' || $value === 'FAIL') {
        return null;
    }
    if (!preg_match('/^([0-9.]+)\s*(ns|μs|us|ms|s|m)?$/u', $value, $m)) {
        return null;
    }
    $n = (float) $m[1];
    return match ($m[2] ?? 'us') {
        'ns'        => $n / 1000.0,
        'μs', 'us'  => $n,
        'ms'        => $n * 1000.0,
        's'         => $n * 1_000_000.0,
        'm'         => $n * 60_000_000.0,
        default     => $n,
    };
}

/** Parse a phpbench-formatted memory string (e.g. "8.0mb") into bytes. */
function parseMemoryToBytes(?string $value): ?float
{
    if ($value === null || $value === '' || $value === 'FAIL') {
        return null;
    }
    if (!preg_match('/^([0-9.]+)\s*(b|kb|mb|gb)?$/i', $value, $m)) {
        return null;
    }
    $n = (float) $m[1];
    return match (strtolower($m[2] ?? 'b')) {
        'b'  => $n,
        'kb' => $n * 1024.0,
        'mb' => $n * 1024.0 * 1024.0,
        'gb' => $n * 1024.0 * 1024.0 * 1024.0,
        default => $n,
    };
}

/** Render a percent delta cell. Bold if |Δ| > $threshold and a baseline was present. */
function renderDeltaPct(?float $current, ?float $baseline, float $threshold = BENCH_HIGHLIGHT_THRESHOLD_PCT): string
{
    if ($current === null || $baseline === null || $baseline == 0.0) {
        return '—';
    }
    $deltaPct = (($current - $baseline) / $baseline) * 100.0;
    if (abs($deltaPct) < 0.05) {
        return '—';
    }
    $sign = $deltaPct > 0 ? '+' : '';
    $cell = sprintf('%s%.1f%%', $sign, $deltaPct);
    return abs($deltaPct) > $threshold ? "**{$cell}**" : $cell;
}

/** Render an integer delta cell (used for compliance counts). Bold if non-zero. */
function renderDeltaInt(?int $current, ?int $baseline, bool $boldNonZero = false): string
{
    if ($current === null || $baseline === null) {
        return '—';
    }
    $delta = $current - $baseline;
    if ($delta === 0) {
        return '—';
    }
    $cell = ($delta > 0 ? '+' : '') . $delta;
    return $boldNonZero ? "**{$cell}**" : $cell;
}

/** Format a value cell (e.g. "1.234ms"). Falls back to em-dash for missing data. */
function fmtCell(?string $value): string
{
    return ($value === null || $value === '') ? '—' : $value;
}

// ---------------------------------------------------------------------------
// Coverage section
// ---------------------------------------------------------------------------

function renderCoverage(?array $cur, ?array $base): string
{
    if ($cur === null) {
        return "### Code Coverage\n\n_No coverage data._\n";
    }
    $covPct      = (float) ($cur['coverage'] ?? 0);
    $stmts       = (int)   ($cur['statements'] ?? 0);
    $coveredStmt = (int)   ($cur['covered_statements'] ?? 0);
    $methods     = (int)   ($cur['methods'] ?? 0);
    $coveredMeth = (int)   ($cur['covered_methods'] ?? 0);
    $methodPct   = $methods > 0 ? ($coveredMeth / $methods) * 100.0 : 0.0;

    $covDelta = '';
    $methDelta = '';
    if ($base !== null) {
        $baseCov = (float) ($base['coverage'] ?? 0);
        $diff = $covPct - $baseCov;
        if (abs($diff) >= 0.005) {
            $sign = $diff > 0 ? '+' : '';
            $covDelta = sprintf(' — Δ %s%.2f%% vs main', $sign, $diff);
        } else {
            $covDelta = ' — no change vs main';
        }

        $baseMethods = (int) ($base['methods'] ?? 0);
        $baseMethCovered = (int) ($base['covered_methods'] ?? 0);
        $baseMethPct = $baseMethods > 0 ? ($baseMethCovered / $baseMethods) * 100.0 : 0.0;
        $methDiff = $methodPct - $baseMethPct;
        if (abs($methDiff) >= 0.005) {
            $sign = $methDiff > 0 ? '+' : '';
            $methDelta = sprintf(' — Δ %s%.2f%%', $sign, $methDiff);
        }
    }

    $body  = "### Code Coverage\n\n";
    $body .= sprintf(
        "**%.2f%%** (%d/%d statements)%s  \n",
        $covPct,
        $coveredStmt,
        $stmts,
        $covDelta,
    );
    $body .= sprintf(
        "Methods: %.2f%% (%d/%d)%s\n",
        $methodPct,
        $coveredMeth,
        $methods,
        $methDelta,
    );
    return $body;
}

// ---------------------------------------------------------------------------
// Benchmarks section — phpdftk-only rows across the three benchmark classes.
// ---------------------------------------------------------------------------

function renderBenchmarks(?array $cur, ?array $base, string $repo): string
{
    if ($cur === null) {
        return "### Benchmarks\n\n_No benchmark data._\n";
    }

    $rows = [];

    // Writer time / Reader time use parseDurationToUs.
    // Writer memory uses parseMemoryToBytes.
    $sections = [
        ['label' => 'Writer time',   'key' => 'GeneratePdfBench_time', 'unit' => 'time'],
        ['label' => 'Writer memory', 'key' => 'GeneratePdfBench_mem',  'unit' => 'mem'],
        ['label' => 'Reader time',   'key' => 'ReadPdfBench_time',     'unit' => 'time'],
    ];

    foreach ($sections as $s) {
        $curLib  = $cur[$s['key']]['phpdftk']  ?? [];
        $baseLib = ($base !== null) ? ($base[$s['key']]['phpdftk'] ?? null) : null;
        if (empty($curLib)) {
            continue;
        }
        ksort($curLib, SORT_NUMERIC);
        foreach ($curLib as $pages => $val) {
            $baseVal = ($baseLib !== null) ? ($baseLib[(string) $pages] ?? null) : null;
            if ($s['unit'] === 'time') {
                $curN  = parseDurationToUs((string) $val);
                $baseN = parseDurationToUs($baseVal !== null ? (string) $baseVal : null);
            } else {
                $curN  = parseMemoryToBytes((string) $val);
                $baseN = parseMemoryToBytes($baseVal !== null ? (string) $baseVal : null);
            }
            $rows[] = sprintf(
                '| %s %dpg | %s | %s | %s |',
                $s['label'],
                (int) $pages,
                fmtCell((string) $val),
                fmtCell($baseVal !== null ? (string) $baseVal : null),
                renderDeltaPct($curN, $baseN),
            );
        }
    }

    if (empty($rows)) {
        return "### Benchmarks\n\n_No phpdftk benchmark rows in current run._\n";
    }

    $body  = "### Benchmarks\n\n";
    $body .= sprintf(
        "phpdftk only · regressions ≥ ±%.0f%% bolded · variance on shared CI runners ≈ ±5–10%%.\n\n",
        BENCH_HIGHLIGHT_THRESHOLD_PCT,
    );
    $body .= "| Bench | This PR | main | Δ |\n";
    $body .= "|---|---|---|---|\n";
    $body .= implode("\n", $rows) . "\n";

    if ($repo !== '') {
        $body .= sprintf(
            "\n[Full benchmark report ↗](https://github.com/%s/blob/_benchmarks/latest/benchmarks.md)\n",
            $repo,
        );
    }
    return $body;
}

// ---------------------------------------------------------------------------
// Compliance section
// ---------------------------------------------------------------------------

function renderCompliance(?array $cur, ?array $base, string $repo): string
{
    if ($cur === null) {
        return "### Compliance\n\n_No compliance data._\n";
    }

    $totals     = $cur['totals']     ?? [];
    $baseTotals = $base['totals']    ?? null;
    $overall    = $cur['overall_status'] ?? '?';
    $statusIcon = match ($overall) {
        'PASS' => '✅',
        'FAIL' => '❌',
        'SKIP' => '⏭️',
        default => '❓',
    };

    $totFailedDelta = $baseTotals
        ? renderDeltaInt((int) ($totals['failed'] ?? 0), (int) ($baseTotals['failed'] ?? 0), boldNonZero: true)
        : '—';
    $totLine = sprintf(
        "**Overall: %s %s** | %d/%d tests passed | failed Δ vs main: %s",
        $statusIcon,
        $overall,
        (int) ($totals['passed'] ?? 0),
        (int) ($totals['tests']  ?? 0),
        $totFailedDelta,
    );

    $rows = [];
    foreach (($cur['suites'] ?? []) as $key => $suite) {
        $baseSuite = $base['suites'][$key] ?? null;
        $icon = match ($suite['status'] ?? '') {
            'PASS' => '✅',
            'FAIL' => '❌',
            'WARN' => '⚠️',
            'SKIP' => '⏭️',
            default => '❓',
        };
        $current = sprintf('%s %d/%d', $icon, (int) $suite['passed'], (int) $suite['tests']);
        $baseCell = $baseSuite
            ? sprintf('%d/%d', (int) $baseSuite['passed'], (int) $baseSuite['tests'])
            : '—';

        $delta = $baseSuite
            ? renderDeltaInt((int) $suite['failed'], (int) $baseSuite['failed'], boldNonZero: true)
            : '—';

        $rows[] = sprintf(
            '| %s | %s | %s | %s |',
            htmlspecialchars($suite['label'] ?? $key, ENT_NOQUOTES),
            $current,
            $baseCell,
            $delta,
        );
    }

    $body  = "### Compliance\n\n";
    $body .= $totLine . "\n\n";
    $body .= "| Suite | This PR | main | failed Δ |\n";
    $body .= "|---|---|---|---|\n";
    $body .= implode("\n", $rows) . "\n";

    if ($repo !== '') {
        $body .= sprintf(
            "\n[Full compliance report ↗](https://github.com/%s/blob/_compliance/latest/compliance.md)\n",
            $repo,
        );
    }
    return $body;
}

// ---------------------------------------------------------------------------
// Gate verdict computation
// ---------------------------------------------------------------------------

/**
 * Coverage gate: fails if the percentage drop vs main exceeds $thresholdPp.
 * Missing baseline: gate is skipped (verdict = pass, baseline_pct = null).
 */
function computeCoverageVerdict(?array $cur, ?array $base, float $thresholdPp): array
{
    if ($cur === null) {
        return ['failed' => false, 'reason' => 'no_current_data'];
    }
    if ($base === null) {
        return [
            'failed'        => false,
            'reason'        => 'no_baseline',
            'current_pct'   => (float) ($cur['coverage'] ?? 0),
            'baseline_pct'  => null,
            'delta_pp'      => null,
            'threshold_pp'  => $thresholdPp,
        ];
    }
    $cPct = (float) ($cur['coverage']  ?? 0);
    $bPct = (float) ($base['coverage'] ?? 0);
    $deltaPp = $cPct - $bPct;
    return [
        'failed'        => $deltaPp < -$thresholdPp,
        'current_pct'   => $cPct,
        'baseline_pct'  => $bPct,
        'delta_pp'      => round($deltaPp, 2),
        'threshold_pp'  => $thresholdPp,
    ];
}

/**
 * Benchmark gate: fails if any phpdftk row regresses by more than $thresholdPct
 * percent vs main. Returns the list of regressions for inclusion in the report.
 */
function computeBenchmarkVerdict(?array $cur, ?array $base, float $thresholdPct): array
{
    $regressions = [];
    if ($cur === null || $base === null) {
        return [
            'failed'        => false,
            'reason'        => $cur === null ? 'no_current_data' : 'no_baseline',
            'threshold_pct' => $thresholdPct,
            'regressions'   => [],
        ];
    }

    $sections = [
        ['label' => 'Writer time',   'key' => 'GeneratePdfBench_time', 'unit' => 'time'],
        ['label' => 'Writer memory', 'key' => 'GeneratePdfBench_mem',  'unit' => 'mem'],
        ['label' => 'Reader time',   'key' => 'ReadPdfBench_time',     'unit' => 'time'],
    ];

    foreach ($sections as $s) {
        $curLib  = $cur[$s['key']]['phpdftk']  ?? [];
        $baseLib = $base[$s['key']]['phpdftk'] ?? [];
        foreach ($curLib as $pages => $curVal) {
            $baseVal = $baseLib[(string) $pages] ?? null;
            if ($baseVal === null) {
                continue;
            }
            if ($s['unit'] === 'time') {
                $curN  = parseDurationToUs((string) $curVal);
                $baseN = parseDurationToUs((string) $baseVal);
            } else {
                $curN  = parseMemoryToBytes((string) $curVal);
                $baseN = parseMemoryToBytes((string) $baseVal);
            }
            if ($curN === null || $baseN === null || $baseN == 0.0) {
                continue;
            }
            $deltaPct = (($curN - $baseN) / $baseN) * 100.0;
            if ($deltaPct > $thresholdPct) {
                $regressions[] = [
                    'label'      => sprintf('%s %dpg', $s['label'], (int) $pages),
                    'current'    => (string) $curVal,
                    'baseline'   => (string) $baseVal,
                    'delta_pct'  => round($deltaPct, 1),
                ];
            }
        }
    }

    return [
        'failed'        => !empty($regressions),
        'threshold_pct' => $thresholdPct,
        'regressions'   => $regressions,
    ];
}

$coverageVerdict   = computeCoverageVerdict($coverageCurrent, $coverageBaseline, $coverageFailPp);
$benchmarkVerdict  = computeBenchmarkVerdict($benchmarksCurrent, $benchmarksBaseline, $benchmarkFailPct);

// ---------------------------------------------------------------------------
// Assemble
// ---------------------------------------------------------------------------

$out  = COMMENT_MARKER . "\n";
$out .= "## CI Report — `{$shortSha}`\n\n";
$out .= renderCoverage($coverageCurrent, $coverageBaseline) . "\n";
$out .= renderBenchmarks($benchmarksCurrent, $benchmarksBaseline, $repo) . "\n";
$out .= renderCompliance($complianceCurrent, $complianceBaseline, $repo) . "\n";

// Render gate verdict so reviewers see what blocked the merge.
$out .= "### Gate verdict\n\n";
$gateRows = [];
$covIcon  = $coverageVerdict['failed']  ? '❌' : '✅';
$benchIcon = $benchmarkVerdict['failed'] ? '❌' : '✅';
$gateRows[] = sprintf(
    '- %s **Coverage:** drop ≤ %.1fpp — %s',
    $covIcon,
    $coverageFailPp,
    $coverageVerdict['failed']
        ? sprintf('**dropped %.2fpp**', abs($coverageVerdict['delta_pp'] ?? 0))
        : (($coverageVerdict['reason'] ?? '') === 'no_baseline'
            ? 'no main baseline (skipped)'
            : 'within threshold'),
);
$gateRows[] = sprintf(
    '- %s **Benchmarks:** no phpdftk regression > %.0f%% — %s',
    $benchIcon,
    $benchmarkFailPct,
    $benchmarkVerdict['failed']
        ? sprintf('**%d regression(s) above threshold**', count($benchmarkVerdict['regressions']))
        : (($benchmarkVerdict['reason'] ?? '') === 'no_baseline'
            ? 'no main baseline (skipped)'
            : 'within threshold'),
);
$out .= implode("\n", $gateRows) . "\n";

if (!empty($benchmarkVerdict['regressions'])) {
    $out .= "\n  Regressions above threshold:\n";
    foreach ($benchmarkVerdict['regressions'] as $r) {
        $out .= sprintf(
            "  - `%s`: %s vs %s (+%.1f%%)\n",
            $r['label'],
            $r['current'],
            $r['baseline'],
            $r['delta_pct'],
        );
    }
}

if (!empty($missingBaselines)) {
    $out .= sprintf(
        "\n_No `main` baseline yet for: %s — first run after this change merges will populate deltas._\n",
        implode(', ', array_keys($missingBaselines)),
    );
}

if ($repo !== '' && $prNumber !== '' && $commitSha !== '') {
    $out .= sprintf(
        "\n[Full coverage HTML ↗](https://github.com/%s/tree/_coverage/pr/%s/%s)\n",
        $repo,
        $prNumber,
        $commitSha,
    );
}

echo $out;

// Emit a machine-readable verdict so the CI can decide whether to fail the
// check after the comment has been posted. Always exit 0 so the comment posts.
if ($verdictOutputPath !== '') {
    $verdict = [
        'coverage'   => $coverageVerdict,
        'benchmarks' => $benchmarkVerdict,
        'overall_failed' => $coverageVerdict['failed'] || $benchmarkVerdict['failed'],
    ];
    file_put_contents(
        $verdictOutputPath,
        json_encode($verdict, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n",
    );
}
