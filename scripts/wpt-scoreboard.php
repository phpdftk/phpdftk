#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * WPT regression gate.
 *
 * Runs `wpt run --json` for each bucket declared in scripts/wpt-baseline.json,
 * compares the current in-scope PASS COUNT against the committed baseline, and
 * exits non-zero when any bucket drops below `baseline - tolerance`.
 *
 * Why count, not percentage: the corpus total drifts as the WPT submodule is
 * bumped, so a percentage can fall while the engine is unchanged. Pass counts
 * are stable. `tolerance` absorbs the ±1 rasterisation jitter that exists even
 * settler-OFF.
 *
 * Usage:
 *   php scripts/wpt-scoreboard.php                 # gate every bucket
 *   php scripts/wpt-scoreboard.php --only=html,svg # gate a subset (CI sharding)
 *   php scripts/wpt-scoreboard.php --update        # rewrite baseline to current
 *   php scripts/wpt-scoreboard.php --json=out.json # also dump the scoreboard
 *   php scripts/wpt-scoreboard.php --root=<corpus> # override the WPT root
 *
 * Settler is forced OFF for determinism (no Playwright dependency in CI).
 */

$repoRoot = dirname(__DIR__);
$baselinePath = $repoRoot . '/scripts/wpt-baseline.json';
$wptBin = $repoRoot . '/packages/wpt-harness/bin/wpt';

$opts = [];
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--')) {
        $eq = strpos($arg, '=');
        $opts[$eq === false ? substr($arg, 2) : substr($arg, 2, $eq - 2)]
            = $eq === false ? true : substr($arg, $eq + 1);
    }
}

$update = isset($opts['update']);
$onlyRaw = is_string($opts['only'] ?? null) ? $opts['only'] : null;
$only = $onlyRaw !== null ? array_filter(array_map('trim', explode(',', $onlyRaw))) : null;
$rootArg = is_string($opts['root'] ?? null) ? $opts['root'] : null;
$scoreboardOut = is_string($opts['json'] ?? null) ? $opts['json'] : null;

$baseline = json_decode((string) file_get_contents($baselinePath), true);
if (!is_array($baseline) || !isset($baseline['buckets']) || !is_array($baseline['buckets'])) {
    fwrite(STDERR, "wpt-scoreboard: malformed baseline at $baselinePath\n");
    exit(2);
}
$tolerance = (int) ($baseline['tolerance'] ?? 0);

$rows = [];
$regressed = [];
$improved = [];

foreach ($baseline['buckets'] as $name => $spec) {
    if ($only !== null && !in_array($name, $only, true)) {
        continue;
    }
    $filter = (string) ($spec['filter'] ?? '');
    if ($filter === '') {
        fwrite(STDERR, "wpt-scoreboard: bucket '$name' has no filter\n");
        exit(2);
    }
    $base = (int) ($spec['pass'] ?? 0);

    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($wptBin)
        . ' run --filter=' . escapeshellarg($filter) . ' --json';
    if ($rootArg !== null) {
        $cmd .= ' --root=' . escapeshellarg($rootArg);
    }
    $env = 'WPT_DISABLE_DOM_SETTLER=1 ';

    fwrite(STDERR, "▸ $name ($filter) …\n");
    $raw = shell_exec($env . $cmd);
    $data = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($data) || !isset($data['pass'], $data['inScopeTotal'])) {
        fwrite(STDERR, "wpt-scoreboard: no JSON from `$cmd`\n  output: " . trim((string) $raw) . "\n");
        exit(2);
    }

    $cur = (int) $data['pass'];
    $delta = $cur - $base;
    $status = $delta < -$tolerance ? 'REGRESSED' : ($delta > 0 ? 'improved' : 'ok');
    if ($status === 'REGRESSED') {
        $regressed[] = $name;
    } elseif ($status === 'improved') {
        $improved[] = $name;
    }

    $rows[] = [
        'name' => $name,
        'filter' => $filter,
        'base' => $base,
        'cur' => $cur,
        'inScopeTotal' => (int) $data['inScopeTotal'],
        'delta' => $delta,
        'status' => $status,
        'rate' => (float) ($data['inScopePassRate'] ?? 0.0),
    ];

    if ($update) {
        $baseline['buckets'][$name]['pass'] = $cur;
        $baseline['buckets'][$name]['inScopeTotal'] = (int) $data['inScopeTotal'];
    }
}

if ($rows === []) {
    fwrite(STDERR, "wpt-scoreboard: no buckets selected" . ($only ? " for --only=$onlyRaw" : '') . "\n");
    exit(2);
}

// ── Report ────────────────────────────────────────────────────────────
printf("\n%-10s %9s %9s %8s   %-9s %s\n", 'bucket', 'baseline', 'current', 'Δ', 'status', 'pass %');
printf("%s\n", str_repeat('─', 64));
foreach ($rows as $r) {
    printf(
        "%-10s %9d %9d %+8d   %-9s %.2f%%\n",
        $r['name'], $r['base'], $r['cur'], $r['delta'], $r['status'], $r['rate'] * 100,
    );
}
printf("%s\n", str_repeat('─', 64));
printf("tolerance: ±%d   regressed: %d   improved: %d\n\n", $tolerance, count($regressed), count($improved));

if ($scoreboardOut !== null) {
    file_put_contents($scoreboardOut, json_encode([
        'tolerance' => $tolerance,
        'buckets' => $rows,
        'regressed' => $regressed,
        'improved' => $improved,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
}

if ($update) {
    file_put_contents(
        $baselinePath,
        json_encode($baseline, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n",
    );
    fwrite(STDERR, "baseline updated → $baselinePath\n");
    exit(0);
}

if ($regressed !== []) {
    fwrite(STDERR, "✗ WPT regression in: " . implode(', ', $regressed) . "\n");
    fwrite(STDERR, "  If intentional, refresh the baseline: php scripts/wpt-scoreboard.php --update\n");
    exit(1);
}

if ($improved !== []) {
    fwrite(STDERR, "✓ no regressions — improvements in: " . implode(', ', $improved) . " (run --update to bank them)\n");
} else {
    fwrite(STDERR, "✓ no regressions\n");
}
exit(0);
