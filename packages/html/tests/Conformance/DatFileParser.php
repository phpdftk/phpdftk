<?php

declare(strict_types=1);

namespace Phpdftk\Html\Tests\Conformance;

/**
 * Parser for the html5lib-tests `.dat` file format.
 *
 * Each `.dat` file contains a sequence of test cases separated by blank lines.
 * Each case has labelled sections introduced by `#`-prefixed headers:
 *
 *   #data
 *   <p>hello
 *   #errors
 *   (3,3): expected-doctype-but-got-start-tag
 *   #document
 *   | <html>
 *   |   <head>
 *   |   <body>
 *   |     <p>
 *   |       "hello"
 *
 * Optional headers include `#document-fragment` (context element for fragment
 * parsing), `#script-on` / `#script-off` (scripting flag override), and
 * `#new-errors` (additional errors after spec changes). See
 * https://github.com/html5lib/html5lib-tests for the canonical format.
 */
final class DatFileParser
{
    /** @return list<DatTestCase> */
    public static function parseFile(string $path): array
    {
        $bytes = file_get_contents($path);
        if ($bytes === false) {
            throw new \RuntimeException("Cannot read $path");
        }
        return self::parseString($bytes);
    }

    /** @return list<DatTestCase> */
    public static function parseString(string $contents): array
    {
        // Split into test cases on double-newline boundaries that introduce
        // the next `#data` section. We can't naively split on `\n\n` because
        // some sections contain blank lines internally.
        $cases = [];
        $lines = preg_split('/\R/', $contents);
        if ($lines === false) {
            return [];
        }

        $i = 0;
        $count = count($lines);
        while ($i < $count) {
            if ($lines[$i] !== '#data') {
                $i++;
                continue;
            }
            // Found a new case; parse it.
            $case = new DatTestCase();
            $i++;
            $section = 'data';
            $sectionBuffer = [];
            $sections = [];

            while ($i < $count) {
                $line = $lines[$i];
                // Section header — but `#` inside `#data` content is allowed
                // if the next test case hasn't started yet. We detect the
                // next case by lookahead for the canonical sequence: blank
                // line then `#data` (or `#errors` if `#data` was empty).
                if (preg_match('/^#(data|errors|new-errors|document|document-fragment|script-on|script-off)$/', $line)) {
                    $sections[$section] = $sectionBuffer;
                    $section = substr($line, 1);
                    $sectionBuffer = [];
                    $i++;
                    continue;
                }
                // Section terminator: blank line followed by `#data` starts the next case.
                if ($line === '' && $i + 1 < $count && $lines[$i + 1] === '#data') {
                    break;
                }
                $sectionBuffer[] = $line;
                $i++;
            }
            $sections[$section] = $sectionBuffer;

            // Materialise.
            $case->data = isset($sections['data']) ? implode("\n", $sections['data']) : '';
            $case->expectedDocument = isset($sections['document']) ? implode("\n", $sections['document']) : '';
            $case->expectedErrors = $sections['errors'] ?? [];
            $case->fragmentContext = isset($sections['document-fragment'])
                ? trim(implode('', $sections['document-fragment']))
                : null;
            $case->scriptingEnabled = isset($sections['script-on']) ? true
                : (isset($sections['script-off']) ? false : null);

            $case->index = count($cases) + 1;
            $cases[] = $case;
            // skip the blank line if present
            if ($i < $count && $lines[$i] === '') {
                $i++;
            }
        }
        return $cases;
    }
}
