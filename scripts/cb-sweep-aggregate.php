<?php

declare(strict_types=1);

/**
 * Fold N per-shard cb-sweep JSONs into a single combined report.
 *
 * Usage:
 *   php cb-sweep-aggregate.php shard-*.json > combined.json
 *
 * The cb-sweep JSON schema (see `wpt cb-sweep --json=…`) carries:
 *   {
 *     "root":     "<wpt-root>",
 *     "engines":  ["chromium", "firefox", "webkit"],
 *     "fixtures": <int>,
 *     "ours_errors": <int>,
 *     "per_directory": { "<dir>": { "count": N, "means": { "<engine>": <float|null> } } },
 *     "overall": { "count": N, "means": { ... } },
 *     "fixtures_detail": [ { "path": ..., "dir": ..., "ours": { "<engine>": <float> } }, ... ]
 *   }
 *
 * Folding is a straight sum: re-aggregate from `fixtures_detail`
 * (the only carrier of the raw AE per fixture) so the merged means
 * are weighted correctly across shards.
 */

$paths = array_slice($argv, 1);
if ($paths === []) {
    fwrite(STDERR, "usage: cb-sweep-aggregate.php <shard.json> [<shard.json> ...]\n");
    exit(2);
}

$engines = [];
$totalFixtures = 0;
$totalErrors = 0;
$perDirSums = [];
$perDirCounts = [];
$perDirEngineSums = [];
$perDirEngineCounts = [];
$allFixtures = [];

foreach ($paths as $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "skip missing shard JSON: $path\n");
        continue;
    }
    $decoded = json_decode((string) file_get_contents($path), associative: true);
    if (!is_array($decoded)) {
        fwrite(STDERR, "skip malformed shard JSON: $path\n");
        continue;
    }
    foreach (($decoded['engines'] ?? []) as $e) {
        if (!in_array($e, $engines, true)) {
            $engines[] = $e;
        }
    }
    $totalFixtures += (int) ($decoded['fixtures'] ?? 0);
    $totalErrors += (int) ($decoded['ours_errors'] ?? 0);
    foreach (($decoded['fixtures_detail'] ?? []) as $entry) {
        $dir = (string) ($entry['dir'] ?? '(root)');
        $perDirCounts[$dir] = ($perDirCounts[$dir] ?? 0) + 1;
        foreach (($entry['ours'] ?? []) as $engine => $ae) {
            $perDirEngineSums[$dir][$engine] = ($perDirEngineSums[$dir][$engine] ?? 0.0) + (float) $ae;
            $perDirEngineCounts[$dir][$engine] = ($perDirEngineCounts[$dir][$engine] ?? 0) + 1;
        }
        $allFixtures[] = $entry;
    }
}

// Derive per-directory means + overall means from the folded sums.
$perDirectory = [];
$overallSums = [];
$overallCounts = [];
$overallCount = 0;
ksort($perDirCounts);
foreach ($perDirCounts as $dir => $count) {
    $overallCount += $count;
    $means = [];
    foreach ($engines as $engine) {
        $c = $perDirEngineCounts[$dir][$engine] ?? 0;
        $means[$engine] = $c === 0
            ? null
            : ($perDirEngineSums[$dir][$engine] / $c);
        $overallSums[$engine] = ($overallSums[$engine] ?? 0.0)
            + ($perDirEngineSums[$dir][$engine] ?? 0.0);
        $overallCounts[$engine] = ($overallCounts[$engine] ?? 0) + $c;
    }
    $perDirectory[$dir] = ['count' => $count, 'means' => $means];
}
$overallMeans = [];
foreach ($engines as $engine) {
    $c = $overallCounts[$engine] ?? 0;
    $overallMeans[$engine] = $c === 0 ? null : $overallSums[$engine] / $c;
}

echo json_encode([
    'engines' => $engines,
    'fixtures' => $totalFixtures,
    'ours_errors' => $totalErrors,
    'per_directory' => $perDirectory,
    'overall' => ['count' => $overallCount, 'means' => $overallMeans],
    'fixtures_detail' => $allFixtures,
], JSON_PRETTY_PRINT) . "\n";
