<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Tests\File;

use Phpdftk\Pdf\Core\Document\Catalog;
use Phpdftk\Pdf\Core\Document\DPartRoot;
use Phpdftk\Pdf\Core\Document\PageTree;
use Phpdftk\Pdf\Core\File\IncrementalWriter;
use Phpdftk\Pdf\Core\File\PdfFileWriter;
use Phpdftk\Pdf\Core\File\VersionRequirementException;
use Phpdftk\Pdf\Core\File\VersionRequirementResolver;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Reader\PdfReader;
use PHPUnit\Framework\TestCase;

class IncrementalWriterVersionTest extends TestCase
{
    protected function setUp(): void
    {
        VersionRequirementResolver::clearCache();
    }

    private function makeSimplePdf(PdfVersion $version = PdfVersion::V1_7): string
    {
        $writer = new PdfFileWriter(version: $version);
        $catalog = new Catalog();
        $writer->setCatalog($catalog);
        $pageTree = new PageTree();
        $writer->register($pageTree);
        $catalog->pages = new PdfReference($pageTree->objectNumber);
        return $writer->generate();
    }

    public function testVersionFromReader(): void
    {
        $pdf = $this->makeSimplePdf(PdfVersion::V1_5);
        $reader = PdfReader::fromString($pdf);
        $writer = IncrementalWriter::fromReader($reader, $pdf);

        $this->assertSame(PdfVersion::V1_5, $writer->getPdfVersion());
        $this->assertFalse($writer->wasVersionBumped());
    }

    public function testAutoBumpSetsFlag(): void
    {
        $pdf = $this->makeSimplePdf(PdfVersion::V1_7);
        $reader = PdfReader::fromString($pdf);
        $writer = IncrementalWriter::fromReader($reader, $pdf);

        $dPartRoot = new DPartRoot(new PdfReference(1));
        $writer->addNewObject($dPartRoot);

        $this->assertTrue($writer->wasVersionBumped());
        $this->assertSame(PdfVersion::V2_0, $writer->getPdfVersion());
        $this->assertNotEmpty($writer->getVersionWarnings());
    }

    public function testNoBumpNoFlag(): void
    {
        $pdf = $this->makeSimplePdf(PdfVersion::V1_7);
        $reader = PdfReader::fromString($pdf);
        $writer = IncrementalWriter::fromReader($reader, $pdf);

        // PageTree is a basic object — no version requirement
        $pageTree = new PageTree();
        $pageTree->objectNumber = 2;
        $pageTree->generationNumber = 0;
        $writer->addModifiedObject($pageTree);

        $this->assertFalse($writer->wasVersionBumped());
    }

    public function testStrictModeThrows(): void
    {
        $pdf = $this->makeSimplePdf(PdfVersion::V1_4);
        $reader = PdfReader::fromString($pdf);
        $writer = IncrementalWriter::fromReader($reader, $pdf);
        $writer->setStrictVersionMode(true);

        $this->expectException(VersionRequirementException::class);

        $dPartRoot = new DPartRoot(new PdfReference(1));
        $writer->addNewObject($dPartRoot);
    }

    public function testStrictDeprecationThrows(): void
    {
        $pdf = $this->makeSimplePdf(PdfVersion::V2_0);
        $reader = PdfReader::fromString($pdf);
        $writer = IncrementalWriter::fromReader($reader, $pdf);
        $writer->setStrictDeprecation(true);

        $this->expectException(\Phpdftk\Pdf\Core\File\DeprecatedFeatureException::class);

        $movie = new \Phpdftk\Pdf\Core\Multimedia\Movie(
            new \Phpdftk\Pdf\Core\FileSpec\FileSpec('test.pdf'),
        );
        $writer->addNewObject($movie);
    }

    public function testStrictDeprecationAllowsBelowRemoval(): void
    {
        $pdf = $this->makeSimplePdf(PdfVersion::V1_7);
        $reader = PdfReader::fromString($pdf);
        $writer = IncrementalWriter::fromReader($reader, $pdf);
        $writer->setStrictDeprecation(true);

        $movie = new \Phpdftk\Pdf\Core\Multimedia\Movie(
            new \Phpdftk\Pdf\Core\FileSpec\FileSpec('test.pdf'),
        );
        $writer->addNewObject($movie);

        // Should warn but not throw
        $depWarnings = array_filter(
            $writer->getVersionWarnings(),
            fn($w) => str_contains($w, 'deprecated'),
        );
        $this->assertNotEmpty($depWarnings);
    }
}
