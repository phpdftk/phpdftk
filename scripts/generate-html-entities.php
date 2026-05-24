<?php

declare(strict_types=1);

/**
 * Generate the full WHATWG named character reference table from the spec's
 * `entities.json` and write it as a PHP constant array.
 *
 * Source: https://html.spec.whatwg.org/entities.json
 *
 * Usage:
 *   php scripts/generate-html-entities.php [path-to-entities.json] [out-path]
 *
 * The script:
 *  - reads entities.json (defaults to vendor-data/whatwg/entities.json)
 *  - normalises each entry: strips the leading '&', preserves the trailing ';' or not
 *  - encodes the resolved codepoints to UTF-8
 *  - writes the PHP file with the TABLE and NO_SEMICOLON_ALLOWED constants
 *
 * Re-run when the spec adds new entries. The output file is checked into the
 * repo so production code has no runtime dependency on fetching the spec.
 */

$jsonPath = $argv[1] ?? __DIR__ . '/../vendor-data/whatwg/entities.json';
$outPath = $argv[2] ?? __DIR__ . '/../packages/html/src/Tokenizer/NamedCharacterReferences.php';

if (!is_file($jsonPath)) {
    fwrite(STDERR, "entities.json not found at: $jsonPath\n");
    fwrite(STDERR, "Download from https://html.spec.whatwg.org/entities.json\n");
    exit(1);
}

$raw = file_get_contents($jsonPath);
if ($raw === false) {
    fwrite(STDERR, "Cannot read $jsonPath\n");
    exit(1);
}

/** @var array<string, array{codepoints: list<int>, characters: string}> $data */
$data = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);

// Normalise: spec keys are like "&amp;" or "&amp" (legacy); store without leading '&'.
$table = [];
$noSemicolon = [];
foreach ($data as $key => $entry) {
    if (!str_starts_with($key, '&')) {
        continue;
    }
    $name = substr($key, 1); // strip the leading '&'
    $resolved = $entry['characters'] ?? null;
    if (!is_string($resolved)) {
        continue;
    }
    $table[$name] = $resolved;
    if (!str_ends_with($name, ';')) {
        $base = $name;
        $noSemicolon[$base] = true;
    }
}

// Sort by length descending then alphabetically for stable output (longest
// match should naturally win because the tokenizer's longest-match loop
// favours longer keys; the array order is informational only).
uksort($table, static function (string $a, string $b): int {
    return strlen($b) <=> strlen($a) ?: strcmp($a, $b);
});

$noSemicolonList = array_keys($noSemicolon);
sort($noSemicolonList);

// Numeric replacement table is independent of entities.json — pulled from
// WHATWG §13.2.5.80 verbatim.
$numericReplacements = [
    0x80 => 0x20AC, 0x82 => 0x201A, 0x83 => 0x0192, 0x84 => 0x201E,
    0x85 => 0x2026, 0x86 => 0x2020, 0x87 => 0x2021, 0x88 => 0x02C6,
    0x89 => 0x2030, 0x8A => 0x0160, 0x8B => 0x2039, 0x8C => 0x0152,
    0x8E => 0x017D, 0x91 => 0x2018, 0x92 => 0x2019, 0x93 => 0x201C,
    0x94 => 0x201D, 0x95 => 0x2022, 0x96 => 0x2013, 0x97 => 0x2014,
    0x98 => 0x02DC, 0x99 => 0x2122, 0x9A => 0x0161, 0x9B => 0x203A,
    0x9C => 0x0153, 0x9E => 0x017E, 0x9F => 0x0178,
];

$out = "<?php\n\n";
$out .= "declare(strict_types=1);\n\n";
$out .= "namespace Phpdftk\\Html\\Tokenizer;\n\n";
$out .= "/**\n";
$out .= " * Named character references per WHATWG HTML §13.5.\n";
$out .= " *\n";
$out .= " * GENERATED FILE — do not hand-edit. Regenerate via\n";
$out .= " *   php scripts/generate-html-entities.php\n";
$out .= " * after pulling a fresh copy of\n";
$out .= " *   https://html.spec.whatwg.org/entities.json\n";
$out .= " *\n";
$out .= " * Entries are sorted by name length descending so the longest-match\n";
$out .= " * lookup in the tokenizer's NamedCharacterReference state can short-circuit\n";
$out .= " * as soon as a match is found.\n";
$out .= " */\n";
$out .= "final class NamedCharacterReferences\n{\n";
$out .= "    /** @var array<string, string> name (with optional trailing ;) → resolved UTF-8 string */\n";
$out .= "    public const array TABLE = [\n";
foreach ($table as $name => $resolved) {
    $out .= sprintf(
        "        %s => %s,\n",
        var_export($name, true),
        encodeAsPhpString($resolved),
    );
}
$out .= "    ];\n\n";
$out .= "    /** @var list<string> names that may omit the trailing ; (legacy entries) */\n";
$out .= "    public const array NO_SEMICOLON_ALLOWED = [\n";
foreach ($noSemicolonList as $name) {
    $out .= sprintf("        %s,\n", var_export($name, true));
}
$out .= "    ];\n\n";
$out .= "    /** @var array<int, int> Windows-1252-compatibility remappings per WHATWG §13.2.5.80 */\n";
$out .= "    public const array NUMERIC_REPLACEMENTS = [\n";
foreach ($numericReplacements as $from => $to) {
    $out .= sprintf("        0x%02X => 0x%04X,\n", $from, $to);
}
$out .= "    ];\n";
$out .= "}\n";

if (file_put_contents($outPath, $out) === false) {
    fwrite(STDERR, "Failed to write $outPath\n");
    exit(1);
}

printf("Wrote %d entries (%d legacy without semicolon) to %s\n",
    count($table), count($noSemicolonList), $outPath);

/** Encode a UTF-8 string as a PHP literal preferring \\u{} escapes for non-printables. */
function encodeAsPhpString(string $s): string
{
    // For most cases var_export does the right thing; for non-printables use a
    // sequence of \u{XXXX} escapes which round-trip cleanly.
    $needsEscape = false;
    foreach (mb_str_split($s, 1, 'UTF-8') as $ch) {
        $cp = mb_ord($ch, 'UTF-8');
        if ($cp === false || $cp < 0x20 || $cp === 0x7F || $cp > 0x7E) {
            $needsEscape = true;
            break;
        }
    }
    if (!$needsEscape) {
        return var_export($s, true);
    }
    $out = '"';
    foreach (mb_str_split($s, 1, 'UTF-8') as $ch) {
        $cp = mb_ord($ch, 'UTF-8') ?: 0;
        if ($cp >= 0x20 && $cp <= 0x7E && $cp !== 0x22 && $cp !== 0x5C) {
            $out .= $ch;
        } else {
            $out .= sprintf('\u{%X}', $cp);
        }
    }
    $out .= '"';
    return $out;
}
