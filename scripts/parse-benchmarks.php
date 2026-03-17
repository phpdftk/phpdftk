<?php
declare(strict_types=1);

$lines = [];
while (($line = fgets(STDIN)) !== false) {
    $lines[] = rtrim($line);
}

// Find the aggregate table (lines between the last +---+ separator block)
$tableLines = [];
$inTable = false;
$headerSkipped = false;
foreach ($lines as $line) {
    if (str_starts_with($line, '+') && str_contains($line, 'benchmark')) {
        continue;
    }
    if (str_starts_with($line, '| benchmark')) {
        $inTable = true;
        $headerSkipped = false;
        continue;
    }
    if ($inTable && str_starts_with($line, '+')) {
        if (!$headerSkipped) {
            $headerSkipped = true;
            continue;
        }
        continue;
    }
    if ($inTable && str_starts_with($line, '|')) {
        $tableLines[] = $line;
    }
}

// Parse rows
$rows = [];
foreach ($tableLines as $line) {
    $parts = array_map('trim', explode('|', $line));
    $parts = array_values(array_filter($parts, fn($p) => $p !== ''));
    if (count($parts) < 7) {
        continue;
    }
    // phpbench aggregate has columns: benchmark, subject, set, revs, its, mem_peak, mode, rstdev
    // The "set" column is always empty and gets filtered out, leaving 7 columns
    [$benchmark, $subject, $revs, $its, $memPeak, $mode, $rstdev] = $parts;
    if (empty($subject)) {
        continue;
    }
    $rows[] = compact('benchmark', 'subject', 'memPeak', 'mode', 'rstdev');
}

// Parse subject into library + pages
function parseSubject(string $subject): array
{
    $libraryMap = [
        'Phpdftk'  => 'phpdftk',
        'Tcpdf'    => 'TCPDF',
        'Fpdf'     => 'FPDF',
        'Mpdf'     => 'mPDF',
        'Dompdf'   => 'Dompdf',
    ];
    foreach ($libraryMap as $key => $name) {
        if (str_contains($subject, $key)) {
            preg_match('/(\d+)Pages?$/', $subject, $m);
            return [$name, (int)($m[1] ?? 1)];
        }
    }
    return [$subject, 0];
}

$pageCounts = [1, 5, 10, 50, 100];
$libraries  = ['phpdftk', 'FPDF', 'TCPDF', 'mPDF', 'Dompdf'];

// Organize data
$timeData   = []; // [benchmark_class][library][pages] = mode
$memData    = []; // [benchmark_class][library][pages] = mem_peak

foreach ($rows as $row) {
    [$lib, $pages] = parseSubject($row['subject']);
    $bc = $row['benchmark'];
    $timeData[$bc][$lib][$pages] = $row['mode'];
    $memData[$bc][$lib][$pages]  = $row['memPeak'];
}

$date    = date('Y-m-d H:i:s T');
$phpVer  = PHP_VERSION;

// Which page counts are present across all data?
$presentPages = [];
foreach ($timeData as $bc => $libData) {
    foreach ($libData as $lib => $pageData) {
        foreach (array_keys($pageData) as $p) {
            $presentPages[$p] = true;
        }
    }
}
ksort($presentPages);
$presentPages = array_keys($presentPages);

/**
 * @param array<string, array<string, array<int, string>>> $data
 * @param array<int, string> $libraries
 * @param array<int, int> $pages
 */
function buildTable(array $data, array $libraries, array $pages, string $benchClass): string
{
    if (empty($data[$benchClass])) {
        return "_No data_\n";
    }
    $header = '| Library | ' . implode(' | ', array_map(fn($p) => "{$p} page" . ($p === 1 ? '' : 's'), $pages)) . ' |';
    $sep    = '|---|' . implode('', array_map(fn($_) => '---|', $pages));
    $rows   = [$header, $sep];
    foreach ($libraries as $lib) {
        if (!isset($data[$benchClass][$lib])) {
            continue;
        }
        $cells = [$lib];
        foreach ($pages as $p) {
            $cells[] = $data[$benchClass][$lib][$p] ?? '—';
        }
        $rows[] = '| ' . implode(' | ', $cells) . ' |';
    }
    return implode("\n", $rows) . "\n";
}

$timeTableGenerate = buildTable($timeData, $libraries, $presentPages, 'GeneratePdfBench');
$memTableGenerate  = buildTable($memData,  $libraries, $presentPages, 'GeneratePdfBench');
$timeTableMemory   = buildTable($timeData, $libraries, $presentPages, 'MemoryBench');
$memTableMemory    = buildTable($memData,  $libraries, $presentPages, 'MemoryBench');

// Raw output (last aggregate table from output)
$rawLines = [];
$capture = false;
foreach ($lines as $line) {
    if (str_starts_with($line, '+') && str_contains($line, '-----')) {
        $capture = true;
    }
    if ($capture) {
        $rawLines[] = $line;
    }
}
$rawTable = implode("\n", $rawLines);

$md = <<<MD
# Benchmark Results

> **Auto-generated.** Run `scripts/benchmark` from the repo root to update this file.

Generated: {$date}
PHP: {$phpVer}
Environment: no opcache, no xdebug

---

## Generation Time — `GeneratePdfBench`

{$timeTableGenerate}
## Peak Memory — `GeneratePdfBench`

{$memTableGenerate}
## Generation Time — `MemoryBench`

{$timeTableMemory}
## Peak Memory — `MemoryBench`

{$memTableMemory}
---

## Raw phpbench Output

```
{$rawTable}
```
MD;

file_put_contents(__DIR__ . '/../docs/benchmarks.md', $md);
echo "docs/benchmarks.md written.\n";
