<?php

declare(strict_types=1);

/**
 * One-off diagnostic: walks each html5lib-tests .dat file with a per-case
 * timeout to find which inputs hang the parser. Run via:
 *   php scripts/probe-html5lib.php [optional/pattern]
 *
 * Outputs a tab-separated table: file<TAB>case-index<TAB>status<TAB>id.
 */

require __DIR__ . '/../vendor/autoload.php';

use Phpdftk\Html\Parser;
use Phpdftk\Html\Tests\Conformance\DatFileParser;

$pattern = $argv[1] ?? '*.dat';
$dir = __DIR__ . '/../vendor-data/html5lib-tests/tree-construction';
$files = glob("$dir/$pattern") ?: [];
sort($files);

foreach ($files as $datFile) {
    $name = basename($datFile, '.dat');
    $cases = DatFileParser::parseFile($datFile);
    foreach ($cases as $i => $case) {
        if ($case->fragmentContext !== null) {
            continue;
        }
        fprintf(STDERR, "→ %s case %d: %s\n", $name, $i, $case->id());
        $start = microtime(true);
        try {
            $parser = new Parser(new \Phpdftk\Html\ParserOptions(
                scriptingEnabled: $case->scriptingEnabled ?? false,
            ));
            $parser->parseDocument($case->data);
            $status = 'pass';
        } catch (\PHPUnit\Framework\AssertionFailedError $e) {
            $status = 'fail';
        } catch (\Throwable $e) {
            $status = 'error:' . $e::class;
        }
        $elapsed = microtime(true) - $start;
        printf("%s\t%d\t%s\t%.3fs\t%s\n", $name, $i, $status, $elapsed, $case->id());
        if ($elapsed > 5.0) {
            fprintf(STDERR, "WARNING: slow case (>5s): %s #%d\n", $name, $i);
        }
    }
}
