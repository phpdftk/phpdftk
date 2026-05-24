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
 *
 * Writer-level benchmarks (`benchLevelN…`) use the level label as the
 * "library" so each level shows on its own row of the table.
 */
function parseSubject(string $subject): array
{
    // Writer-level subjects look like `benchLevel1PdfWriter10Pages` etc.
    // Tables subjects look like `benchLevel3PdfTable100Rows`.
    if (preg_match('/Level(\d)(Pdf|PdfDoc|PdfWriter)/', $subject, $lm)) {
        $level = (int) $lm[1];
        $labels = [
            1 => 'PdfWriter (Level 1)',
            2 => 'PdfDoc (Level 2)',
            3 => 'Pdf (Level 3)',
        ];
        $name = $labels[$level] ?? "Level {$level}";
        preg_match('/(\d+)(?:Pages?|Rows|Items)$/', $subject, $m);
        return [$name, (int)($m[1] ?? 1), 'scaling'];
    }

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
$writerLevelLibraries = ['Pdf (Level 3)', 'PdfDoc (Level 2)', 'PdfWriter (Level 1)'];
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

// Page counts present for the writer-level comparison.
$writerLevelPages = [];
if (isset($timeData['WriterLevelsBench'])) {
    foreach ($timeData['WriterLevelsBench'] as $lib => $keyData) {
        foreach (array_keys($keyData) as $p) {
            if (is_int($p)) {
                $writerLevelPages[$p] = true;
            }
        }
    }
}
ksort($writerLevelPages);
$writerLevelPages = array_keys($writerLevelPages);

// Row counts present for the tables comparison.
$tableRowCounts = [];
if (isset($timeData['TablesBench'])) {
    foreach ($timeData['TablesBench'] as $lib => $keyData) {
        foreach (array_keys($keyData) as $r) {
            if (is_int($r)) {
                $tableRowCounts[$r] = true;
            }
        }
    }
}
ksort($tableRowCounts);
$tableRowCounts = array_keys($tableRowCounts);

// Item counts present for the lists comparison.
$listItemCounts = [];
if (isset($timeData['ListsBench'])) {
    foreach ($timeData['ListsBench'] as $lib => $keyData) {
        foreach (array_keys($keyData) as $i) {
            if (is_int($i)) {
                $listItemCounts[$i] = true;
            }
        }
    }
}
ksort($listItemCounts);
$listItemCounts = array_keys($listItemCounts);

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
 * Build a table with numeric-key columns. `$unit` controls the column
 * label suffix (e.g. "page" → "1 page / 10 pages"; "row" → "10 rows").
 */
function buildTable(array $data, array $libraries, array $keys, string $benchClass, string $unit = 'page'): string
{
    if (empty($data[$benchClass])) {
        return "_No data_\n";
    }
    $header = '| Library | ' . implode(' | ', array_map(fn($k) => "{$k} {$unit}" . ($k === 1 ? '' : 's'), $keys)) . ' |';
    $sep    = '|---|' . implode('', array_map(fn($_) => '---|', $keys));
    $rows   = [$header, $sep];
    foreach ($libraries as $lib) {
        if (!isset($data[$benchClass][$lib])) {
            continue;
        }
        $cells = [$lib];
        foreach ($keys as $k) {
            $cells[] = $data[$benchClass][$lib][$k] ?? '—';
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
$timeTableWriterLevels = buildTable($timeData, $writerLevelLibraries, $writerLevelPages, 'WriterLevelsBench');
$memTableWriterLevels  = buildTable($memData,  $writerLevelLibraries, $writerLevelPages, 'WriterLevelsBench');
$timeTableTables       = buildTable($timeData, $writerLevelLibraries, $tableRowCounts, 'TablesBench', 'row');
$memTableTables        = buildTable($memData,  $writerLevelLibraries, $tableRowCounts, 'TablesBench', 'row');
$timeTableLists        = buildTable($timeData, $writerLevelLibraries, $listItemCounts, 'ListsBench', 'item');
$memTableLists         = buildTable($memData,  $writerLevelLibraries, $listItemCounts, 'ListsBench', 'item');
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
## Writer Levels Comparison — `WriterLevelsBench`

Same workload (N pages with heading + body text) rendered through each
writer level, so the abstraction overhead is visible directly. Lower is
better; the higher-level APIs (`Pdf` → `PdfDoc` → `PdfWriter`) trade
some performance for ergonomics.

### Generation Time

{$timeTableWriterLevels}
### Peak Memory

{$memTableWriterLevels}
## Tables — `TablesBench`

Table rendering through `Pdf::addTable()` (Level 3, flow-paginated)
and `Writer\\Page::drawTable()` (Level 2, positioned). Both share the
same underlying `TableRenderer`; the delta isolates the cost of the
flow-layout engine.

### Generation Time

{$timeTableTables}
### Peak Memory

{$memTableTables}
## Lists — `ListsBench`

Bullet-list rendering through `Pdf::addList()` (Level 3) and
`Writer\\Page::drawList()` (Level 2). Both share `ListRenderer`.

### Generation Time

{$timeTableLists}
### Peak Memory

{$memTableLists}
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

@mkdir(__DIR__ . '/../docs/generated', 0755, true);
file_put_contents(__DIR__ . '/../docs/generated/benchmarks.md', $md);
echo "docs/generated/benchmarks.md written.\n";

// Emit a structured JSON copy for downstream consumers (PR-comment delta
// computation). Values remain phpbench-formatted strings (e.g. "1.234ms",
// "8.0mb"); consumers are responsible for normalization.
$json = [
    'generated_at'         => $date,
    'php_version'          => $phpVer,
    'GeneratePdfBench_time' => $timeData['GeneratePdfBench'] ?? new stdClass(),
    'GeneratePdfBench_mem'  => $memData['GeneratePdfBench']  ?? new stdClass(),
    'MemoryBench_time'      => $timeData['MemoryBench']      ?? new stdClass(),
    'MemoryBench_mem'       => $memData['MemoryBench']       ?? new stdClass(),
    'WriterLevelsBench_time' => $timeData['WriterLevelsBench'] ?? new stdClass(),
    'WriterLevelsBench_mem'  => $memData['WriterLevelsBench']  ?? new stdClass(),
    'TablesBench_time'      => $timeData['TablesBench']      ?? new stdClass(),
    'TablesBench_mem'       => $memData['TablesBench']       ?? new stdClass(),
    'ListsBench_time'       => $timeData['ListsBench']       ?? new stdClass(),
    'ListsBench_mem'        => $memData['ListsBench']        ?? new stdClass(),
    'ReadPdfBench_time'     => $timeData['ReadPdfBench']     ?? new stdClass(),
    'ReadPdfBench_mem'      => $memData['ReadPdfBench']      ?? new stdClass(),
    'compat_time'           => $compatTime,
    'compat_mem'            => $compatMem,
];
file_put_contents(
    __DIR__ . '/../docs/generated/benchmarks.json',
    json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n",
);
echo "docs/generated/benchmarks.json written.\n";
