<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Reader\Tests\Compliance;

use Phpdftk\Tests\Support\DockerToolResult;
use Phpdftk\Tests\Support\VeraPdfValidationTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tier 2 — veraPDF negative compliance tests against Isartor/Bavaria corpora.
 *
 * These PDFs are known-bad: each one deliberately violates PDF/A rules.
 * We assert that veraPDF correctly identifies them as non-compliant.
 *
 * Run with: vendor/bin/phpunit --group tier2-pdfa
 */
#[Group('tier2')]
#[Group('tier2-pdfa')]
class Tier2PdfACorpusTest extends TestCase
{
    use VeraPdfValidationTrait;

    private const CORPUS_DIR = __DIR__ . '/../../../../../vendor-data/verapdf-corpus';

    // -----------------------------------------------------------------------
    // PDF/A-1b corpus (Isartor)
    // -----------------------------------------------------------------------

    /** @return iterable<string, array{string}> */
    public static function pdfA1bProvider(): iterable
    {
        yield from self::pdfFilesIn(self::CORPUS_DIR . '/PDF_A-1b', 'PDF_A-1b');
    }

    #[DataProvider('pdfA1bProvider')]
    public function testPdfA1bNonCompliance(string $path): void
    {
        $rawResult = $this->runVeraPdfRaw($path, '1b');
        $output = $rawResult instanceof DockerToolResult ? $rawResult->output : $rawResult;

        self::assertStringContainsString(
            'isCompliant="false"',
            $output,
            sprintf('Expected veraPDF to report non-compliance for %s', basename($path)),
        );
    }

    // -----------------------------------------------------------------------
    // PDF/A-2b corpus (Bavaria)
    // -----------------------------------------------------------------------

    /** @return iterable<string, array{string}> */
    public static function pdfA2bProvider(): iterable
    {
        yield from self::pdfFilesIn(self::CORPUS_DIR . '/PDF_A-2b', 'PDF_A-2b');
    }

    #[DataProvider('pdfA2bProvider')]
    public function testPdfA2bNonCompliance(string $path): void
    {
        $rawResult = $this->runVeraPdfRaw($path, '2b');
        $output = $rawResult instanceof DockerToolResult ? $rawResult->output : $rawResult;

        self::assertStringContainsString(
            'isCompliant="false"',
            $output,
            sprintf('Expected veraPDF to report non-compliance for %s', basename($path)),
        );
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
}
