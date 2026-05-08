<?php

declare(strict_types=1);

// Parse --tools argument
$tools = '';
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--tools=')) {
        $tools = substr($arg, 8);
    }
}
$availableTools = array_filter(explode(',', $tools));

/**
 * Parse a JUnit XML file into structured results.
 *
 * @return array{tests: int, passed: int, failed: int, skipped: int, errors: int, time: float, cases: list<array{name: string, class: string, status: string, time: float, message: string}>}
 */
function parseJunitXml(string $path): array
{
    $result = [
        'tests' => 0,
        'passed' => 0,
        'failed' => 0,
        'skipped' => 0,
        'errors' => 0,
        'time' => 0.0,
        'cases' => [],
    ];

    if (!file_exists($path)) {
        return $result;
    }

    $xml = @simplexml_load_file($path);
    if ($xml === false) {
        return $result;
    }

    // PHPUnit JUnit XML has <testsuites><testsuite ...><testcase .../></testsuite></testsuites>
    // Collect all testcase elements recursively
    $testcases = [];
    collectTestcases($xml, $testcases);

    foreach ($testcases as $tc) {
        $result['tests']++;

        $name = (string) ($tc['name'] ?? '');
        $class = (string) ($tc['class'] ?? '');
        $time = (float) ($tc['time'] ?? 0);

        $status = 'passed';
        $message = '';

        if (isset($tc->skipped)) {
            $status = 'skipped';
            $result['skipped']++;
            $message = (string) $tc->skipped;
        } elseif (isset($tc->failure)) {
            $status = 'failed';
            $result['failed']++;
            $message = (string) ($tc->failure['message'] ?? $tc->failure);
        } elseif (isset($tc->error)) {
            $status = 'error';
            $result['errors']++;
            $message = (string) ($tc->error['message'] ?? $tc->error);
        } else {
            $result['passed']++;
        }

        $result['time'] += $time;

        $result['cases'][] = [
            'name' => $name,
            'class' => $class,
            'status' => $status,
            'time' => $time,
            'message' => $message,
        ];
    }

    return $result;
}

function collectTestcases(SimpleXMLElement $el, array &$cases): void
{
    foreach ($el->children() as $child) {
        if ($child->getName() === 'testcase' && isset($child['name'])) {
            $cases[] = $child;
        } else {
            collectTestcases($child, $cases);
        }
    }
}

function statusEmoji(string $status): string
{
    return match ($status) {
        'passed' => 'PASS',
        'failed', 'error' => 'FAIL',
        'skipped' => 'SKIP',
        default => '?',
    };
}

function suiteStatus(array $result, bool $toolAvailable): string
{
    if ($result['tests'] === 0) {
        return 'NO TESTS';
    }
    if ($result['failed'] > 0 || $result['errors'] > 0) {
        return 'FAIL';
    }
    if ($result['skipped'] === $result['tests']) {
        return 'SKIP';
    }
    if (!$toolAvailable && $result['passed'] > 0) {
        return 'WARN';
    }
    return 'PASS';
}

function suiteStatusIcon(string $status): string
{
    return match ($status) {
        'PASS' => '&#x2705;',  // green check
        'FAIL' => '&#x274C;',  // red X
        'WARN' => '&#x26A0;&#xFE0F;',  // warning
        'SKIP' => '&#x23ED;&#xFE0F;',  // skip
        default => '&#x2753;',  // question
    };
}

function formatTime(float $seconds): string
{
    if ($seconds < 0.001) {
        return '<1ms';
    }
    if ($seconds < 1.0) {
        return round($seconds * 1000) . 'ms';
    }
    return round($seconds, 2) . 's';
}

function shortClass(string $fqcn): string
{
    $parts = explode('\\', $fqcn);
    return end($parts);
}

function buildDetailTable(array $result): string
{
    if (empty($result['cases'])) {
        return "_No test results available._\n";
    }

    $lines = [];
    $lines[] = '| Test | Class | Status | Time |';
    $lines[] = '|---|---|---|---|';

    foreach ($result['cases'] as $case) {
        $statusLabel = statusEmoji($case['status']);
        $lines[] = sprintf(
            '| %s | %s | %s | %s |',
            $case['name'],
            shortClass($case['class']),
            $statusLabel,
            formatTime($case['time']),
        );
    }

    return implode("\n", $lines) . "\n";
}

// --- Main ---

$buildDir = __DIR__ . '/../build/compliance';

$suites = [
    'qpdf' => [
        'label' => 'QPDF',
        'description' => 'Structural integrity (xref, page tree, streams, linearization, encryption)',
        'file' => $buildDir . '/qpdf.xml',
        'toolKeys' => ['qpdf-docker', 'qpdf-local'],
        'tier' => 1,
    ],
    'arlington' => [
        'label' => 'Arlington PDF Model',
        'description' => 'Dictionary-level spec conformance (keys, types, required fields, version constraints)',
        'file' => $buildDir . '/arlington.xml',
        'toolKeys' => ['arlington'],
        'tier' => 1,
    ],
    'verapdf' => [
        'label' => 'veraPDF',
        'description' => 'PDF/A archival conformance (ISO 19005)',
        'file' => $buildDir . '/verapdf.xml',
        'toolKeys' => ['verapdf-docker', 'verapdf-local'],
        'tier' => 1,
    ],
    'tier2' => [
        'label' => 'Test Corpora',
        'description' => 'Reader robustness against Poppler, QPDF, PDFium, PDFBox, and veraPDF corpus PDFs',
        'file' => $buildDir . '/tier2.xml',
        'toolKeys' => ['poppler-corpus', 'qpdf-corpus', 'verapdf-corpus', 'pdfium-corpus', 'pdfbox-corpus'],
        'tier' => 2,
    ],
    'tier3' => [
        'label' => 'Matterhorn (PDF/UA)',
        'description' => 'PDF/UA-1 accessibility validation via veraPDF ua1 profile',
        'file' => $buildDir . '/tier3.xml',
        'toolKeys' => ['verapdf-docker', 'verapdf-local'],
        'tier' => 3,
    ],
    'tier4' => [
        'label' => 'JHOVE + PDF 2.0 + Security + Preflight',
        'description' => 'Format validation, PDF 2.0 reference parsing, security lint, PDF/A-1b cross-validation',
        'file' => $buildDir . '/tier4.xml',
        'toolKeys' => ['jhove-docker', 'jhove-local', 'pdf20examples', 'pdfid-docker', 'pdfid-local', 'pdfbox-preflight-docker'],
        'tier' => 4,
    ],
];

$results = [];
foreach ($suites as $key => $suite) {
    $results[$key] = parseJunitXml($suite['file']);
}

// Check tool availability
$toolAvailability = [];
foreach ($suites as $key => $suite) {
    $toolAvailability[$key] = false;
    foreach ($suite['toolKeys'] as $tk) {
        if (in_array($tk, $availableTools, true)) {
            $toolAvailability[$key] = true;
            break;
        }
    }
}

$date = date('Y-m-d H:i:s T');
$phpVer = PHP_VERSION;

// Build summary table
$summaryRows = [];
foreach ($suites as $key => $suite) {
    $r = $results[$key];
    $status = suiteStatus($r, $toolAvailability[$key]);
    $icon = suiteStatusIcon($status);
    $summaryRows[] = sprintf(
        '| %s %s | %s | %d | %d | %d | %d | %s |',
        $icon,
        $suite['label'],
        $status,
        $r['tests'],
        $r['passed'],
        $r['failed'] + $r['errors'],
        $r['skipped'],
        formatTime($r['time']),
    );
}
$summaryTable = implode("\n", $summaryRows);

// Totals
$totalTests = array_sum(array_column($results, 'tests'));
$totalPassed = array_sum(array_column($results, 'passed'));
$totalFailed = array_sum(array_map(fn($r) => $r['failed'] + $r['errors'], $results));
$totalSkipped = array_sum(array_column($results, 'skipped'));
$totalTime = array_sum(array_column($results, 'time'));

$totalTimeFormatted = formatTime($totalTime);

// Overall status
$overallStatus = 'PASS';
if ($totalFailed > 0) {
    $overallStatus = 'FAIL';
} elseif ($totalSkipped === $totalTests || $totalTests === 0) {
    $overallStatus = 'SKIP';
}

// Build detail sections grouped by tier
$tier1DetailSections = '';
$tier2DetailSections = '';
$tier3DetailSections = '';
$tier4DetailSections = '';
foreach ($suites as $key => $suite) {
    $r = $results[$key];
    $status = suiteStatus($r, $toolAvailability[$key]);
    $section = "### {$suite['label']} — {$suite['description']}\n\n";

    if (!$toolAvailability[$key] && $r['passed'] > 0 && $r['failed'] === 0 && $r['errors'] === 0) {
        if ($key === 'qpdf') {
            $section .= "> **Note:** QPDF was not available during this run. Tests passed but structural validation was silently skipped. Install QPDF or build the Docker image for full validation.\n\n";
        } elseif ($key === 'tier2') {
            $section .= "> **Note:** One or more test corpora submodules are not initialized. Run `git submodule update --init` to fetch them.\n\n";
        } else {
            $section .= "> **Note:** The validation tool was not available during this run.\n\n";
        }
    }

    $section .= sprintf(
        "**%d tests** | %d passed | %d failed | %d skipped | %s\n\n",
        $r['tests'],
        $r['passed'],
        $r['failed'] + $r['errors'],
        $r['skipped'],
        formatTime($r['time']),
    );

    $section .= buildDetailTable($r);
    $section .= "\n";

    match ($suite['tier']) {
        1 => $tier1DetailSections .= $section,
        2 => $tier2DetailSections .= $section,
        3 => $tier3DetailSections .= $section,
        4 => $tier4DetailSections .= $section,
    };
}

// Build Tier 2-4 sections
$tier2 = <<<MD
## Tier 2 — Test Corpora

PDF test file collections from major PDF implementations for stress-testing reader error tolerance and edge-case handling.

| Suite | Source | Status |
|---|---|---|
| Poppler Test Files | gitlab.freedesktop.org/poppler/test | Integrated |
| QPDF Test Suite | github.com/qpdf/qpdf | Integrated |
| veraPDF Corpus (Isartor/Bavaria) | github.com/veraPDF/veraPDF-corpus | Integrated |
| PDFium Test Resources | github.com/chromium/pdfium | Integrated |
| Apache PDFBox Test Files | github.com/apache/pdfbox | Integrated |

{$tier2DetailSections}
MD;

$tier3 = <<<MD
## Tier 3 — Accessibility Compliance

Tools and test suites for PDF/UA (Universal Accessibility) and WCAG compliance.

| Suite | What it validates | Status |
|---|---|---|
| Matterhorn Protocol (via veraPDF) | PDF/UA-1 failure conditions | Integrated |
| PAC (PDF Accessibility Checker) | PDF/UA-1, PDF/UA-2, WCAG 2.1 | N/A (Windows only) |
| W3C PDF Techniques | 23 WCAG 2.x techniques (PDF1-PDF23) | N/A (reference docs) |
| PDF/UA-2 Test Resources | ISO 14289-2:2024 (PDF 2.0 based) | N/A (emerging standard) |

{$tier3DetailSections}
MD;

$tier4 = <<<MD
## Tier 4 — Reference and Conformance Targets

General-purpose validation tools and reference document collections.

| Suite | What it validates | Status |
|---|---|---|
| JHOVE | Format validation and characterization | Integrated |
| PDF 2.0 Examples | Reference PDF 2.0 documents | Integrated |
| Didier Stevens' pdfid | Security analysis (JS, auto-open, etc.) | Integrated |
| Apache PDFBox Preflight | PDF/A-1b cross-validation | Integrated |
| pdfaPilot (Callas) | Commercial PDF/A, PDF/X, PDF/UA, PDF/VT | N/A (commercial license) |

{$tier4DetailSections}
MD;

$md = <<<MD
# Compliance Report Card

> **Auto-generated.** Run `scripts/compliance` from the repo root to update this file.

Generated: {$date}
PHP: {$phpVer}

---

## Summary

**Overall: {$overallStatus}** | {$totalTests} tests | {$totalPassed} passed | {$totalFailed} failed | {$totalSkipped} skipped | {$totalTimeFormatted}

| Suite | Status | Tests | Passed | Failed | Skipped | Time |
|---|---|---|---|---|---|---|
{$summaryTable}

---

## Tier 1 — Integrated

These validation tools run as part of the test suite. See [docs/validations/](validations/) for integration details.

{$tier1DetailSections}
---

{$tier2}

---

{$tier3}

---

{$tier4}
MD;

@mkdir(__DIR__ . '/../docs/generated', 0755, true);
file_put_contents(__DIR__ . '/../docs/generated/compliance.md', $md);
echo "docs/generated/compliance.md written.\n";

// Emit a structured JSON copy for PR-comment delta computation. Per-suite
// counts and overall totals are preserved; per-test detail (cases) is
// intentionally omitted to keep the file small enough to fetch as a baseline.
$jsonSuites = [];
foreach ($suites as $key => $suite) {
    $r = $results[$key];
    $jsonSuites[$key] = [
        'label'      => $suite['label'],
        'tier'       => $suite['tier'],
        'tool_available' => $toolAvailability[$key],
        'status'     => suiteStatus($r, $toolAvailability[$key]),
        'tests'      => $r['tests'],
        'passed'     => $r['passed'],
        'failed'     => $r['failed'] + $r['errors'],
        'skipped'    => $r['skipped'],
        'time_s'     => round($r['time'], 3),
    ];
}
$json = [
    'generated_at'   => $date,
    'php_version'    => $phpVer,
    'overall_status' => $overallStatus,
    'totals' => [
        'tests'   => $totalTests,
        'passed'  => $totalPassed,
        'failed'  => $totalFailed,
        'skipped' => $totalSkipped,
        'time_s'  => round($totalTime, 3),
    ],
    'suites' => $jsonSuites,
];
file_put_contents(
    __DIR__ . '/../docs/generated/compliance.json',
    json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n",
);
echo "docs/generated/compliance.json written.\n";

// Calculate compliance percentage for badge
if ($totalTests > 0) {
    $pct = (int) round(($totalPassed / $totalTests) * 100);
    echo "Compliance: {$pct}% ({$totalPassed}/{$totalTests})\n";
}

// Hard-fail the script if any suite is FAIL / NO TESTS / WARN. SKIP and PASS
// are tolerated. Markdown + JSON have already been written above so the CI
// step `if: always()` artifact upload still gets the data.
$badStatuses = ['FAIL', 'NO TESTS', 'WARN'];
$badSuites = [];
foreach ($jsonSuites as $key => $entry) {
    if (in_array($entry['status'], $badStatuses, true)) {
        $badSuites[] = sprintf('%s (%s)', $entry['label'], $entry['status']);
    }
}
if (!empty($badSuites)) {
    fwrite(STDERR, "Compliance gate: failing suites — " . implode(', ', $badSuites) . "\n");
    exit(1);
}
