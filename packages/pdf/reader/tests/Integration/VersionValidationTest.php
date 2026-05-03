<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Reader\Tests\Integration;

use Phpdftk\Pdf\Core\Document\Catalog;
use Phpdftk\Pdf\Core\Document\OCG;
use Phpdftk\Pdf\Core\Document\OCPropertiesDict;
use Phpdftk\Pdf\Core\Document\PageTree;
use Phpdftk\Pdf\Core\File\PdfFileWriter;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Reader\PdfReader;
use PHPUnit\Framework\TestCase;

class VersionValidationTest extends TestCase
{
    public function testValidateVersionNoWarningsForCompliantPdf(): void
    {
        // Simple PDF at version 1.7 — no issues
        $writer = new PdfFileWriter(version: PdfVersion::V1_7);
        $catalog = new Catalog();
        $writer->setCatalog($catalog);
        $pageTree = new PageTree();
        $writer->register($pageTree);
        $catalog->pages = new PdfReference($pageTree->objectNumber);

        $bytes = $writer->generate();
        $reader = PdfReader::fromString($bytes);

        $warnings = $reader->validateVersion();
        $this->assertEmpty($warnings);
    }

    public function testValidateVersionDetectsXrefStreamVersionMismatch(): void
    {
        // Build a PDF with xref streams — version will auto-bump to 1.5
        $writer = new PdfFileWriter(useXRefStream: true, version: PdfVersion::V1_5);
        $catalog = new Catalog();
        $writer->setCatalog($catalog);
        $pageTree = new PageTree();
        $writer->register($pageTree);
        $catalog->pages = new PdfReference($pageTree->objectNumber);

        $bytes = $writer->generate();

        // Reader should detect xref stream and see it matches 1.5 — no warning
        $reader = PdfReader::fromString($bytes);
        $this->assertSame(PdfVersion::V1_5, $reader->getPdfVersion());

        $warnings = $reader->validateVersion();
        $this->assertEmpty($warnings);
    }

    public function testGetPdfVersionAndEffectiveVersion(): void
    {
        $writer = new PdfFileWriter(version: PdfVersion::V1_7);
        $catalog = new Catalog();
        $writer->setCatalog($catalog);
        $pageTree = new PageTree();
        $writer->register($pageTree);
        $catalog->pages = new PdfReference($pageTree->objectNumber);

        $bytes = $writer->generate();
        $reader = PdfReader::fromString($bytes);

        $this->assertSame(PdfVersion::V1_7, $reader->getPdfVersion());
        $this->assertSame(PdfVersion::V1_7, $reader->getEffectiveVersion());
    }

    public function testGetEffectiveVersionUsesHigherCatalogVersion(): void
    {
        // Generate a PDF 2.0 (which sets catalog /Version to 2.0)
        $writer = new PdfFileWriter(version: PdfVersion::V2_0);
        $catalog = new Catalog();
        $writer->setCatalog($catalog);
        $pageTree = new PageTree();
        $writer->register($pageTree);
        $catalog->pages = new PdfReference($pageTree->objectNumber);

        $bytes = $writer->generate();
        $reader = PdfReader::fromString($bytes);

        $this->assertSame(PdfVersion::V2_0, $reader->getEffectiveVersion());
    }
}
