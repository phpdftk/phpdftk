<?php

declare(strict_types=1);

/**
 * Find which specific fixtures cause our renderer to OOM.
 *
 * Walks the same discovery surface as `wpt cb-sweep` (with the same
 * crashtest skip), renders each fixture in a SUBPROCESS so an
 * uncatchable Fatal in one fixture doesn't take the scan down, and
 * reports any fixture whose subprocess hit a memory_limit OOM or
 * exceeded a peak-memory threshold.
 *
 * Pair with diagnose-leak-spikes (which scans within one process)
 * — that one misses the fixtures that blow the limit BEFORE the
 * print loop can record them. This one survives them.
 *
 * Usage:
 *   php scripts/diagnose-oom-fixtures.php \
 *       [--scope=<file>] [--include=<csv-globs>] [--exclude=<csv-globs>]
 *       [--shard=K/N] [--max=<N>] [--memory-limit=<size>]
 *       [--peak-threshold-mb=<int>] [--json=<path>]
 *
 * Defaults: rendering scope, no shard split, memory_limit=512M (so
 * OOMs are obvious — most healthy fixtures use < 30 MB), peak
 * threshold 100 MB (flag fixtures that allocate more than this
 * without crashing).
 */

require __DIR__ . '/../vendor/autoload.php';

// -----------------------------------------------------------------------
// Argument parsing
// -----------------------------------------------------------------------
$opts = [];
$positional = [];
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--')) {
        $eq = strpos($arg, '=');
        if ($eq !== false) {
            $opts[substr($arg, 2, $eq - 2)] = substr($arg, $eq + 1);
        } else {
            $opts[substr($arg, 2)] = true;
        }
    } else {
        $positional[] = $arg;
    }
}

$wptRoot = __DIR__ . '/../vendor-data/wpt';
$scope = $opts['scope'] ?? __DIR__ . '/../packages/wpt-harness/scope/rendering.json';
$includesArg = $opts['include'] ?? '';
$excludesArg = $opts['exclude'] ?? '';
$shardSpec = $opts['shard'] ?? '1/1';
$max = (int) ($opts['max'] ?? 0);
$memoryLimit = (string) ($opts['memory-limit'] ?? '512M');
$peakThresholdMb = (int) ($opts['peak-threshold-mb'] ?? 100);
$jsonOut = $opts['json'] ?? null;

// Resolve includes — pick the scope JSON if it exists and no
// --include was passed; otherwise parse --include as csv.
$includes = $includesArg !== ''
    ? array_values(array_filter(array_map('trim', explode(',', $includesArg))))
    : [];
if ($includes === [] && is_file($scope)) {
    $scopeData = json_decode((string) file_get_contents($scope), associative: true);
    if (is_array($scopeData) && isset($scopeData['include']) && is_array($scopeData['include'])) {
        foreach ($scopeData['include'] as $entry) {
            if (is_string($entry) && $entry !== '') {
                $includes[] = $entry;
            }
        }
    }
}
$excludes = $excludesArg !== ''
    ? array_values(array_filter(array_map('trim', explode(',', $excludesArg))))
    : [];

if (!preg_match('#^(\d+)/(\d+)$#', $shardSpec, $sh)) {
    fwrite(STDERR, "--shard must look like K/N\n");
    exit(2);
}
$shardIndex = (int) $sh[1];
$shardCount = (int) $sh[2];

// -----------------------------------------------------------------------
// Discover fixtures (mirrors cb-sweep's logic)
// -----------------------------------------------------------------------
function discover(string $root, array $includes, array $excludes, int $shardIndex, int $shardCount): array
{
    $rootReal = realpath($root) ?: $root;
    $out = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
        $rootReal,
        FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS,
    ));
    foreach ($it as $entry) {
        if (!$entry->isFile()) {
            continue;
        }
        $name = $entry->getFilename();
        if (preg_match('/-ref\.(html?|xht)$/i', $name)) {
            continue;
        }
        if (!preg_match('/\.(html?|xht)$/i', $name)) {
            continue;
        }
        $path = $entry->getPathname();
        if (str_contains($path, '/support/') || str_contains($path, '/crashtests/')) {
            continue;
        }
        $rel = substr($path, strlen($rootReal) + 1);
        if ($includes !== []) {
            $matched = false;
            foreach ($includes as $glob) {
                if (str_contains($glob, '*')
                    ? fnmatch($glob, $rel)
                    : str_starts_with($rel, $glob . '/')
                ) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                continue;
            }
        }
        foreach ($excludes as $glob) {
            if (str_contains($glob, '*')
                ? fnmatch($glob, $rel)
                : str_starts_with($rel, $glob . '/')
            ) {
                continue 2;
            }
        }
        $out[] = $path;
    }
    sort($out);
    if ($shardCount > 1) {
        $out = array_values(array_filter(
            $out,
            static fn(string $p): bool =>
                (crc32($p) % $shardCount) + 1 === $shardIndex,
        ));
    }
    return $out;
}

$fixtures = discover($wptRoot, $includes, $excludes, $shardIndex, $shardCount);
if ($max > 0 && count($fixtures) > $max) {
    $fixtures = array_slice($fixtures, 0, $max);
}

printf("diagnose-oom-fixtures\n");
printf("  scope:           %s\n", $scope);
printf("  fixtures:        %d\n", count($fixtures));
printf("  shard:           %d/%d\n", $shardIndex, $shardCount);
printf("  memory_limit:    %s (subprocess)\n", $memoryLimit);
printf("  peak threshold:  %d MB\n\n", $peakThresholdMb);

// -----------------------------------------------------------------------
// Render each fixture in a subprocess; record OOMs / peak-spikes
// -----------------------------------------------------------------------
$bad = [];
$oomCount = 0;
$spikeCount = 0;
$tStart = microtime(true);
$php = PHP_BINARY;
$worker = __DIR__ . '/_oom-worker.php';

foreach ($fixtures as $i => $path) {
    $cmd = sprintf(
        '%s -d memory_limit=%s %s %s',
        escapeshellcmd($php),
        escapeshellarg($memoryLimit),
        escapeshellarg($worker),
        escapeshellarg($path),
    );
    $out = [];
    $code = -1;
    exec($cmd . ' 2>&1', $out, $code);
    $tail = trim(implode("\n", array_slice($out, -3)));
    $oom = ($code !== 0) && (
        str_contains($tail, 'Allowed memory size')
        || str_contains($tail, 'memory_limit')
    );
    if ($oom) {
        $oomCount++;
        $rel = substr($path, strlen(realpath($wptRoot)) + 1);
        printf("  OOM   %s\n", $rel);
        $bad[] = ['path' => $rel, 'reason' => 'oom', 'msg' => $tail];
    } else {
        // Worker prints "PEAK <bytes>" on success.
        if (preg_match('/^PEAK (\d+)/m', implode("\n", $out), $m)) {
            $peakMb = (int) (((int) $m[1]) / (1024 * 1024));
            if ($peakMb > $peakThresholdMb) {
                $spikeCount++;
                $rel = substr($path, strlen(realpath($wptRoot)) + 1);
                printf("  SPIKE %5d MB  %s\n", $peakMb, $rel);
                $bad[] = ['path' => $rel, 'reason' => 'spike', 'peak_mb' => $peakMb];
            }
        }
    }
    if (($i + 1) % 100 === 0) {
        printf(
            "  ... %5d/%-5d  oom=%d  spike=%d  elapsed=%ds\n",
            $i + 1, count($fixtures), $oomCount, $spikeCount,
            (int) (microtime(true) - $tStart),
        );
    }
}

printf("\nDone in %ds. %d OOMs, %d spikes above %d MB, %d clean.\n",
    (int) (microtime(true) - $tStart),
    $oomCount, $spikeCount, $peakThresholdMb,
    count($fixtures) - $oomCount - $spikeCount,
);

if ($jsonOut !== null) {
    file_put_contents($jsonOut, (string) json_encode([
        'scope' => $scope,
        'memory_limit' => $memoryLimit,
        'peak_threshold_mb' => $peakThresholdMb,
        'oom_count' => $oomCount,
        'spike_count' => $spikeCount,
        'fixtures_scanned' => count($fixtures),
        'bad' => $bad,
    ], JSON_PRETTY_PRINT));
    printf("JSON dump: %s\n", $jsonOut);
}
