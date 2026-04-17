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

/**
 * Parse subject into [library, key, category].
 * category: 'scaling' (page-count key) or 'compat' (feature-name key).
 */
function parseSubject(string $subject): array
{
    $libraryMap = [
        'Phpdftk'  => 'phpdftk',
        'Tcpdf'    => 'TCPDF',
        'Fpdf'     => 'FPDF',
        'Mpdf'     => 'mPDF',
        'Dompdf'   => 'Dompdf',
        'Smalot'   => 'smalot/pdfparser',
        'Fpdi'     => 'setasign/fpdi',
    ];

    // Reader compatibility benchmarks — feature-name keys
    $compatMap = [
        'SpecCompliantXref' => 'spec_xref',
        'XrefStream'        => 'xref_stream',
    ];

    foreach ($libraryMap as $key => $name) {
        if (str_contains($subject, $key)) {
            // Check compatibility benchmarks first
            foreach ($compatMap as $suffix => $featKey) {
                if (str_ends_with($subject, $suffix)) {
                    return [$name, $featKey, 'compat'];
                }
            }
            // Page-count based (scaling)
            preg_match('/(\d+)Pages?$/', $subject, $m);
            return [$name, (int)($m[1] ?? 1), 'scaling'];
        }
    }
    return [$subject, 0, 'scaling'];
}

$libraries  = ['phpdftk', 'FPDF', 'TCPDF', 'mPDF', 'Dompdf'];
$readerLibraries = ['phpdftk', 'smalot/pdfparser', 'setasign/fpdi'];
$compatFeatures = ['spec_xref', 'xref_stream'];
$compatLabels = [
    'spec_xref'   => 'Spec-compliant xref (20-byte SP CR LF)',
    'xref_stream' => 'Cross-reference stream (PDF 1.5)',
];

// Organize data
$timeData    = []; // [benchmark_class][library][key] = mode
$memData     = []; // [benchmark_class][library][key] = mem_peak
$compatTime  = []; // [library][feature] = mode
$compatMem   = []; // [library][feature] = mem_peak

foreach ($rows as $row) {
    [$lib, $key, $category] = parseSubject($row['subject']);
    $bc = $row['benchmark'];

    if ($category === 'compat') {
        $compatTime[$lib][$key] = $row['mode'];
        $compatMem[$lib][$key]  = $row['memPeak'];
    } else {
        $timeData[$bc][$lib][$key] = $row['mode'];
        $memData[$bc][$lib][$key]  = $row['memPeak'];
    }
}

$date    = date('Y-m-d H:i:s T');
$phpVer  = PHP_VERSION;

// Which page counts are present across writer data?
$presentPages = [];
foreach (['GeneratePdfBench', 'MemoryBench'] as $bc) {
    if (!isset($timeData[$bc])) {
        continue;
    }
    foreach ($timeData[$bc] as $lib => $keyData) {
        foreach (array_keys($keyData) as $p) {
            if (is_int($p)) {
                $presentPages[$p] = true;
            }
        }
    }
}
ksort($presentPages);
$presentPages = array_keys($presentPages);

// Reader page counts (may differ from writer page counts)
$readerPages = [];
if (isset($timeData['ReadPdfBench'])) {
    foreach ($timeData['ReadPdfBench'] as $lib => $keyData) {
        foreach (array_keys($keyData) as $p) {
            if (is_int($p)) {
                $readerPages[$p] = true;
            }
        }
    }
}
ksort($readerPages);
$readerPages = array_keys($readerPages);

/**
 * Build a table with page-count columns.
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

/**
 * Build a compatibility table (columns = feature names, values = time or "FAIL").
 */
function buildCompatTable(array $data, array $libraries, array $features, array $labels): string
{
    if (empty($data)) {
        return "_No data_\n";
    }
    $header = '| Library | ' . implode(' | ', array_map(fn($k) => $labels[$k] ?? $k, $features)) . ' |';
    $sep    = '|---|' . implode('', array_map(fn($_) => '---|', $features));
    $rows   = [$header, $sep];
    foreach ($libraries as $lib) {
        $cells = [$lib];
        foreach ($features as $feat) {
            $cells[] = $data[$lib][$feat] ?? 'FAIL';
        }
        $rows[] = '| ' . implode(' | ', $cells) . ' |';
    }
    return implode("\n", $rows) . "\n";
}

$timeTableGenerate = buildTable($timeData, $libraries, $presentPages, 'GeneratePdfBench');
$memTableGenerate  = buildTable($memData,  $libraries, $presentPages, 'GeneratePdfBench');
$timeTableMemory   = buildTable($timeData, $libraries, $presentPages, 'MemoryBench');
$memTableMemory    = buildTable($memData,  $libraries, $presentPages, 'MemoryBench');
$timeTableReader   = buildTable($timeData, $readerLibraries, $readerPages, 'ReadPdfBench');
$memTableReader    = buildTable($memData,  $readerLibraries, $readerPages, 'ReadPdfBench');
// Known parser incompatibilities — these record a time but the parser
// threw an exception and returned without actually parsing the PDF.
$knownFails = [
    'smalot/pdfparser' => ['spec_xref'],
    'setasign/fpdi'    => ['xref_stream'],
];
foreach ($knownFails as $lib => $features) {
    foreach ($features as $feat) {
        $compatTime[$lib][$feat] = 'FAIL';
        $compatMem[$lib][$feat]  = 'FAIL';
    }
}

$compatTimeTable   = buildCompatTable($compatTime, $readerLibraries, $compatFeatures, $compatLabels);
$compatMemTable    = buildCompatTable($compatMem,  $readerLibraries, $compatFeatures, $compatLabels);

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
## Parse Time — `ReadPdfBench`

{$timeTableReader}
## Peak Memory — `ReadPdfBench`

{$memTableReader}
## Compatibility — `ReadPdfBench`

Parse time for PDFs using spec-compliant features. `FAIL` = parser threw an exception.

{$compatTimeTable}
---

## Raw phpbench Output

```
{$rawTable}
```
MD;

file_put_contents(__DIR__ . '/../docs/benchmarks.md', $md);
echo "docs/benchmarks.md written.\n";
