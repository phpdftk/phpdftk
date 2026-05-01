<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Reader\Tests\Compliance;

use ApprLabs\Pdf\Reader\PdfReader;
use ApprLabs\Tests\Support\QpdfValidationTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tier 4 — PDF 2.0 reference document parsing and validation.
 *
 * Parses PDF 2.0 example files from the pdf20examples corpus and asserts
 * they are structurally valid using both PdfReader and QPDF.
 *
 * Run with: vendor/bin/phpunit --group tier4
 */
#[Group('tier4')]
class Tier4Pdf20ExamplesTest extends TestCase
{
    use QpdfValidationTrait;

    private const PDF20_DIR = __DIR__ . '/../../../../../vendor-data/pdf20examples';

    // -----------------------------------------------------------------------
    // Data provider
    // -----------------------------------------------------------------------

    /** @return iterable<string, array{string}> */
    public static function pdf20Provider(): iterable
    {
        $resolved = realpath(self::PDF20_DIR);
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
            $label = 'pdf20/' . substr($path, strlen($resolved) + 1);
            yield $label => [$path];
        }
    }

    // -----------------------------------------------------------------------
    // Tests
    // -----------------------------------------------------------------------

    #[DataProvider('pdf20Provider')]
    public function testPdf20ExampleParseable(string $path): void
    {
        $reader = PdfReader::fromFile($path, strict: false);

        self::assertNotEmpty($reader->getVersion(), 'PDF version should not be empty');
        self::assertGreaterThan(0, $reader->getPageCount(), 'PDF should have at least one page');

        $catalog = $reader->getCatalog();
        self::assertNotNull($catalog, 'Catalog should not be null');
    }

    #[DataProvider('pdf20Provider')]
    public function testPdf20ExampleQpdfValid(string $path): void
    {
        self::assertFileExists($path);
        $this->assertQpdfValid($path);
    }
}
