<?php

declare(strict_types=1);

/**
 * Generate the MathML Core operator dictionary as a PHP constant array.
 *
 * Source: the dictionary that MathML Core §3.2.4 defines and Appendix B
 * tabulates, as published in machine-readable form alongside the Web
 * Platform Tests MathML suite
 * (`mathml/support/operator-dictionary.json`, itself generated from the
 * Unicode `unicode.xml` operator data by WPT's
 * `mathml/tools/operator-dictionary.py`).
 *
 * Usage:
 *   php scripts/generate-operator-dictionary.php [path-to-json] [out-path]
 *
 * The script:
 *  - reads the JSON `{ "<characters> <form>": { lspace, rspace, ...flags } }`
 *  - packs each entry as `[lspaceIndex, rspaceIndex, flagBits]` — spacing is
 *    an integer number of eighteenths of an em (0-7) in the source data, and
 *    the five booleans fit in a bitmask, so the whole 1180-entry table stays
 *    small enough to parse without ceremony
 *  - writes the PHP file with the TABLE constant, keyed
 *    `characters => form => packed`
 *
 * Re-run when the spec's dictionary changes. The output file is checked into
 * the repo so production code has no runtime dependency on the corpus.
 */

$jsonPath = $argv[1] ?? __DIR__ . '/../vendor-data/wpt/mathml/support/operator-dictionary.json';
$outPath = $argv[2] ?? __DIR__ . '/../packages/mathml/src/OperatorDictionaryTable.php';

if (!is_file($jsonPath)) {
    fwrite(STDERR, "operator-dictionary.json not found at: $jsonPath\n");
    fwrite(STDERR, "It ships with the WPT corpus under mathml/support/.\n");
    exit(1);
}

$raw = file_get_contents($jsonPath);
if ($raw === false) {
    fwrite(STDERR, "Cannot read $jsonPath\n");
    exit(1);
}

/** @var array{comment?: string, dictionary: array<string, array<string, int|bool>>} $data */
$data = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
$dictionary = $data['dictionary'] ?? null;
if (!is_array($dictionary)) {
    fwrite(STDERR, "No `dictionary` object in $jsonPath\n");
    exit(1);
}

/**
 * Flag bits. Keep in sync with OperatorDictionary::FLAG_* — the
 * generated table is meaningless without them.
 */
const FLAG_STRETCHY = 1;
const FLAG_SYMMETRIC = 2;
const FLAG_HORIZONTAL = 4;
const FLAG_LARGEOP = 8;
const FLAG_MOVABLELIMITS = 16;

/**
 * Spacing default when the entry omits lspace / rspace: 5 eighteenths,
 * the same value an operator missing from the dictionary entirely gets.
 */
const DEFAULT_SPACE_INDEX = 5;

/** @var array<string, array<string, array{int, int, int}>> $table */
$table = [];
$formsSeen = [];
foreach ($dictionary as $key => $entry) {
    $split = strrpos($key, ' ');
    if ($split === false) {
        fwrite(STDERR, "Malformed dictionary key (no form): " . json_encode($key) . "\n");
        exit(1);
    }
    $characters = substr($key, 0, $split);
    $form = substr($key, $split + 1);
    if ($characters === '') {
        fwrite(STDERR, "Empty operator text in key: " . json_encode($key) . "\n");
        exit(1);
    }
    $formsSeen[$form] = true;

    $lspace = $entry['lspace'] ?? DEFAULT_SPACE_INDEX;
    $rspace = $entry['rspace'] ?? DEFAULT_SPACE_INDEX;
    if (!is_int($lspace) || !is_int($rspace) || $lspace < 0 || $rspace < 0 || $lspace > 7 || $rspace > 7) {
        fwrite(STDERR, "Spacing out of range for " . json_encode($key) . "\n");
        exit(1);
    }

    $flags = 0;
    $flags |= ($entry['stretchy'] ?? false) === true ? FLAG_STRETCHY : 0;
    $flags |= ($entry['symmetric'] ?? false) === true ? FLAG_SYMMETRIC : 0;
    $flags |= ($entry['horizontal'] ?? false) === true ? FLAG_HORIZONTAL : 0;
    $flags |= ($entry['largeop'] ?? false) === true ? FLAG_LARGEOP : 0;
    $flags |= ($entry['movablelimits'] ?? false) === true ? FLAG_MOVABLELIMITS : 0;

    $table[$characters][$form] = [$lspace, $rspace, $flags];
}

ksort($table);

$lines = [];
foreach ($table as $characters => $forms) {
    ksort($forms);
    $parts = [];
    foreach ($forms as $form => [$lspace, $rspace, $flags]) {
        $parts[] = sprintf("'%s' => [%d, %d, %d]", $form, $lspace, $rspace, $flags);
    }
    $lines[] = sprintf(
        "        %s => [%s],",
        phpStringLiteral((string) $characters),
        implode(', ', $parts),
    );
}

/**
 * Render an operator's characters as a PHP single-quoted literal when
 * they are printable ASCII, and as a double-quoted `\u{...}` escape
 * sequence otherwise — a table full of raw combining marks and
 * invisible operators is unreadable and easy to corrupt in an editor.
 */
function phpStringLiteral(string $characters): string
{
    if (preg_match('/^[\x20-\x7E]+$/', $characters) === 1) {
        return "'" . str_replace(['\\', "'"], ['\\\\', "\\'"], $characters) . "'";
    }
    $escaped = '';
    foreach (preg_split('//u', $characters, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $char) {
        $codepoint = mb_ord($char, 'UTF-8');
        if ($codepoint === false) {
            fwrite(STDERR, "Cannot encode operator text: " . bin2hex($characters) . "\n");
            exit(1);
        }
        $escaped .= sprintf('\u{%04X}', $codepoint);
    }

    return '"' . $escaped . '"';
}

$entryCount = 0;
foreach ($table as $forms) {
    $entryCount += count($forms);
}

$forms = implode(', ', array_keys($formsSeen));
$body = implode("\n", $lines);

$out = <<<PHP
<?php

declare(strict_types=1);

namespace Phpdftk\\Mathml;

/**
 * The MathML Core operator dictionary, as data.
 *
 * GENERATED FILE - do not edit by hand. Regenerate with:
 *
 *     php scripts/generate-operator-dictionary.php
 *
 * Source: MathML Core §3.2.4 / Appendix B, via the machine-readable
 * copy that ships with the Web Platform Tests MathML suite
 * (`mathml/support/operator-dictionary.json`), itself generated from
 * the Unicode operator data by WPT's `mathml/tools/operator-dictionary.py`.
 *
 * {$entryCount} (characters, form) pairs across {$forms}.
 *
 * Shape: `characters => form => [lspaceIndex, rspaceIndex, flagBits]`.
 *
 *  - Spacing is stored the way the spec tabulates it: an integer
 *    number of EIGHTEENTHS of an em, 0-7. {@see OperatorDictionary}
 *    converts to em.
 *  - `flagBits` packs the five boolean properties; the bit values are
 *    the `OperatorDictionary::FLAG_*` constants.
 *
 * The packed form keeps a 1180-entry table cheap to parse and keeps
 * the diff of a spec update readable. Consumers should never read
 * this table directly - go through {@see OperatorDictionary::lookup()},
 * which expands an entry into the named-key shape and supplies the
 * spec's fallback for operators the dictionary does not list.
 */
final class OperatorDictionaryTable
{
    /** @var array<string, array<string, array{int, int, int}>> */
    public const array TABLE = [
{$body}
    ];
}

PHP;

if (file_put_contents($outPath, $out) === false) {
    fwrite(STDERR, "Cannot write $outPath\n");
    exit(1);
}

fwrite(STDOUT, sprintf(
    "Wrote %s: %d operators, %d (characters, form) entries.\n",
    $outPath,
    count($table),
    $entryCount,
));
