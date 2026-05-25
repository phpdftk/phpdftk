<?php

declare(strict_types=1);

namespace Phpdftk\Html\Tests\Conformance;

use Phpdftk\Html\Parser;
use Phpdftk\Html\ParserOptions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Runs html5lib-tests tree-construction `.dat` files against our parser and
 * compares the produced DOM to the expected canonical tree.
 *
 * Per the project-decisions strict-conformance posture, every test in every
 * file under `vendor-data/html5lib-tests/tree-construction/` must pass
 * unless explicitly deferred via an entry in `tests/conformance/ignored.txt`.
 * CI hard-fails if a passing test stops passing OR if a test listed in
 * ignored.txt starts passing without being removed.
 *
 * For local development without the submodule, the test exercises the
 * checked-in `tests/conformance/smoke.dat` fixture which has hand-picked
 * cases against the implemented insertion modes. Once
 * `vendor-data/html5lib-tests` is initialised, the full suite runs too.
 */
final class Html5LibTreeConstructionTest extends TestCase
{
    private static function repoRoot(): string
    {
        return dirname(__DIR__, 4);
    }

    private static function smokeFixturePath(): string
    {
        return __DIR__ . '/smoke.dat';
    }

    private static function upstreamCorpusPath(): string
    {
        return self::repoRoot() . '/vendor-data/html5lib-tests/tree-construction';
    }

    /**
     * @return iterable<string, array{string, string}>
     *   keyed by "<file>:<id>", with [file path, test ID prefix] tuples that
     *   the data provider passes to the runner.
     */
    public static function provideSmokeCases(): iterable
    {
        $path = self::smokeFixturePath();
        if (!is_file($path)) {
            yield 'smoke.dat:absent' => [$path, ''];
            return;
        }
        $cases = DatFileParser::parseFile($path);
        foreach ($cases as $i => $case) {
            $key = sprintf('smoke.dat:#%d:%s', $i, $case->id());
            yield $key => [$path, $case->id()];
        }
    }

    #[DataProvider('provideSmokeCases')]
    public function testSmokeFixture(string $path, string $caseId): void
    {
        if (!is_file($path)) {
            self::markTestSkipped("Smoke fixture missing: $path");
            return;
        }
        $cases = DatFileParser::parseFile($path);
        $match = null;
        foreach ($cases as $case) {
            if ($case->id() === $caseId) {
                $match = $case;
                break;
            }
        }
        self::assertNotNull($match, "Case '$caseId' not found in $path");
        $this->runOneCase($match, 'smoke.dat');
    }

    public function testUpstreamCorpusAvailability(): void
    {
        // If the submodule is initialised, run a quick smoke against it.
        // Otherwise this test passes as a no-op, with a hint for developers.
        $dir = self::upstreamCorpusPath();
        if (!is_dir($dir)) {
            self::markTestSkipped(
                'vendor-data/html5lib-tests is not initialised. Run: ' .
                'git submodule add https://github.com/html5lib/html5lib-tests vendor-data/html5lib-tests && ' .
                'git submodule update --init --depth 1 vendor-data/html5lib-tests',
            );
            return;
        }
        $datFiles = glob($dir . '/*.dat') ?: [];
        self::assertNotEmpty($datFiles, 'Expected upstream html5lib-tests/tree-construction to contain .dat files');
    }

    /**
     * Run the upstream tree-construction suite if the submodule is present.
     * Each failing test must be in ignored.txt or the suite fails. Each
     * ignored test must still fail; ignored-but-now-passing also fails.
     */
    public function testUpstreamSuiteRespectsIgnoreLedger(): void
    {
        $dir = self::upstreamCorpusPath();
        if (!is_dir($dir)) {
            self::markTestSkipped('Upstream html5lib-tests submodule not initialised');
            return;
        }
        $datFiles = glob($dir . '/*.dat') ?: [];
        $ignored = $this->loadIgnoredLedger();
        $unexpectedPasses = [];
        $unexpectedFailures = [];

        foreach ($datFiles as $datFile) {
            $name = basename($datFile, '.dat');
            $cases = DatFileParser::parseFile($datFile);
            foreach ($cases as $case) {
                if ($case->fragmentContext !== null) {
                    continue; // Fragment-parser conformance is a separate test path.
                }
                $key = "tree-construction/$name:" . $case->id();
                try {
                    $this->runOneCase($case, $name . '.dat');
                    $passed = true;
                } catch (\PHPUnit\Framework\AssertionFailedError) {
                    $passed = false;
                } catch (\Throwable) {
                    $passed = false;
                }
                $isIgnored = $this->isIgnored($ignored, $key);
                if ($passed && $isIgnored) {
                    $unexpectedPasses[] = $key;
                }
                if (!$passed && !$isIgnored) {
                    $unexpectedFailures[] = $key;
                }
            }
            // Free per-file DOM trees so we don't accumulate ~1500 documents
            // worth of memory across the whole suite.
            unset($cases);
            gc_collect_cycles();
        }

        if ($unexpectedFailures !== []) {
            self::fail(
                "Unexpected failures in upstream suite (add to ignored.txt or fix the parser):\n  - "
                . implode("\n  - ", array_slice($unexpectedFailures, 0, 20))
                . (count($unexpectedFailures) > 20 ? "\n  ... and " . (count($unexpectedFailures) - 20) . ' more' : ''),
            );
        }
        if ($unexpectedPasses !== []) {
            self::fail(
                "Tests in ignored.txt that now pass (remove from ignored.txt):\n  - "
                . implode("\n  - ", $unexpectedPasses),
            );
        }
        self::assertTrue(true, 'Upstream suite + ignored.txt consistent');
    }

    private function runOneCase(DatTestCase $case, string $fileLabel): void
    {
        $options = new ParserOptions(
            scriptingEnabled: $case->scriptingEnabled ?? false,
        );
        $parser = new Parser($options);
        $doc = $parser->parseDocument($case->data);
        $actual = DomTreeSerializer::serialize($doc);
        $expected = $case->expectedDocument;

        // Normalise both: strip trailing whitespace per line, drop trailing blank lines.
        $normalise = static function (string $s): string {
            $lines = preg_split('/\R/', $s) ?: [];
            $lines = array_map('rtrim', $lines);
            while ($lines !== [] && end($lines) === '') {
                array_pop($lines);
            }
            return implode("\n", $lines);
        };
        self::assertSame(
            $normalise($expected),
            $normalise($actual),
            sprintf("Case '%s' in %s\nInput: %s", $case->id(), $fileLabel, $case->data),
        );
    }

    /**
     * Parse the ignored.txt ledger into a set of keys.
     *
     * @return array<string, true>
     */
    private function loadIgnoredLedger(): array
    {
        $path = __DIR__ . '/ignored.txt';
        if (!is_file($path)) {
            return [];
        }
        $out = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            // Strip the ": reason" trailer.
            $colon = strrpos($line, ':');
            if ($colon === false) {
                continue;
            }
            $key = substr($line, 0, $colon);
            $out[trim($key)] = true;
        }
        return $out;
    }

    /** @param array<string, true> $ledger */
    private function isIgnored(array $ledger, string $key): bool
    {
        // The ledger entry prefix-matches the key (an entry can list a file
        // wildcard like "tree-construction/template" to ignore the whole file).
        if (isset($ledger[$key])) {
            return true;
        }
        foreach ($ledger as $entry => $_) {
            if (str_starts_with($key, $entry)) {
                return true;
            }
        }
        return false;
    }
}
