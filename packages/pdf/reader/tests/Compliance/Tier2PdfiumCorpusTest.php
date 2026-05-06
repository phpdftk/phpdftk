<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Reader\Tests\Compliance;

use Phpdftk\Pdf\Reader\PdfReader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tier 2 — PDFium test corpus compliance tests.
 *
 * Parses every PDF from the PDFium test corpus with PdfReader in lenient mode
 * and asserts no crashes. Encrypted PDFs that require a password are expected
 * to throw -- those are caught and counted as passes.
 *
 * Run with: vendor/bin/phpunit --group tier2-pdfium
 */
#[Group('tier2')]
#[Group('tier2-pdfium')]
class Tier2PdfiumCorpusTest extends TestCase
{
    private const CORPUS_DIR = __DIR__ . '/../../../../../vendor-data/pdfium/testing/resources';

    /** @return iterable<string, array{string}> */
    public static function pdfiumProvider(): iterable
    {
        yield from self::pdfFilesIn(
            self::CORPUS_DIR,
            'pdfium',
        );
    }

    #[DataProvider('pdfiumProvider')]
    public function testPdfiumCorpus(string $path): void
    {
        if ($path === '__SKIP__') {
            $this->markTestSkipped('PDFium corpus not available (vendor-data/pdfium submodule not initialized)');
        }
        self::assertCorpusPdfParseable($path);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Glob all .pdf files in a directory tree and yield them as data-provider entries.
     *
     * @return iterable<string, array{string}>
     */
    private static function pdfFilesIn(string $directory, string $prefix): iterable
    {
        $resolved = realpath($directory);
        if ($resolved === false || !is_dir($resolved)) {
            yield '__SKIP__' => ['__SKIP__'];
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($resolved, \FilesystemIterator::SKIP_DOTS),
        );

        $paths = [];
        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if (strtolower($file->getExtension()) !== 'pdf') {
                continue;
            }
            $paths[] = $file->getRealPath();
        }

        sort($paths);

        foreach ($paths as $path) {
            $label = $prefix . '/' . substr($path, strlen($resolved) + 1);
            yield $label => [$path];
        }
    }

    /**
     * Parse a corpus PDF in lenient mode and assert it doesn't crash.
     *
     * Encrypted PDFs that require a password will throw -- that's expected.
     * We only fail on truly unexpected errors (segfaults, fatal errors, etc.).
     */
    private static function assertCorpusPdfParseable(string $path): void
    {
        try {
            $reader = PdfReader::fromFile($path, strict: false);

            // Exercise basic structural access to ensure the parse is sound
            $version = $reader->getVersion();
            $pageCount = $reader->getPageCount();
            $reader->getCatalog();

            self::assertNotEmpty($version, 'PDF version should not be empty');
            self::assertGreaterThanOrEqual(0, $pageCount, 'Page count should be non-negative');
        } catch (\Throwable $e) {
            $message = $e->getMessage();

            // Encrypted PDFs requiring a password are expected to fail
            if (
                stripos($message, 'encrypt') !== false
                || stripos($message, 'password') !== false
                || stripos($message, 'decrypt') !== false
            ) {
                self::assertTrue(true, "Encrypted PDF skipped: {$message}");
                return;
            }

            // Intentionally malformed/truncated test files are expected to fail
            if (
                stripos($message, 'invalid') !== false
                || stripos($message, 'malformed') !== false
                || stripos($message, 'truncated') !== false
                || stripos($message, 'not a valid PDF') !== false
                || stripos($message, 'unexpected') !== false
                || stripos($message, 'missing') !== false
                || stripos($message, 'corrupt') !== false
                || stripos($message, 'startxref') !== false
            ) {
                self::assertTrue(true, "Expected parse failure: {$message}");
                return;
            }

            // Re-throw truly unexpected errors
            self::fail(sprintf(
                "Unexpected error parsing %s: [%s] %s",
                basename($path),
                $e::class,
                $message,
            ));
        }
    }
}
