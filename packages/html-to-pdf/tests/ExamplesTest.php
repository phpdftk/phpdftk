<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Smoke test for the canonical `examples/html-to-pdf/*.php` scripts. Per
 * the project convention (CLAUDE.md), every sample script runs in CI and
 * must produce a PDF whose first bytes start with `%PDF-`. This guarantees
 * the public-facing demos stay buildable as the engine evolves.
 */
final class ExamplesTest extends TestCase
{
    private const string EXAMPLES_DIR = __DIR__ . '/../../../examples/html-to-pdf';

    public static function exampleScripts(): array
    {
        $dir = self::EXAMPLES_DIR;
        if (!is_dir($dir)) {
            return [];
        }
        $files = glob($dir . '/*.php') ?: [];
        $cases = [];
        foreach ($files as $file) {
            $cases[basename($file)] = [$file];
        }
        return $cases;
    }

    #[DataProvider('exampleScripts')]
    public function testExampleScriptProducesValidPdf(string $scriptPath): void
    {
        // Run in an isolated subprocess so the script's globals don't leak
        // into the test runner (the bootstrap defines const + helpers).
        $cmd = sprintf('php %s 2>&1', escapeshellarg($scriptPath));
        $exitCode = 0;
        $output = [];
        exec($cmd, $output, $exitCode);
        self::assertSame(0, $exitCode, "Example failed: " . implode("\n", $output));

        // Each example prints a `Wrote <path>` line — grep the produced
        // PDF path and verify the file begins with `%PDF-`.
        $producedPath = null;
        foreach ($output as $line) {
            if (preg_match('/^Wrote (\S+)/', $line, $m)) {
                $producedPath = $m[1];
                break;
            }
        }
        self::assertNotNull($producedPath, "Example didn't print a Wrote line");
        self::assertFileExists($producedPath);
        $head = (string) file_get_contents($producedPath, length: 8);
        self::assertStringStartsWith('%PDF-', $head);
    }
}
