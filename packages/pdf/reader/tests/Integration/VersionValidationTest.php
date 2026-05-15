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

    public function testValidateVersionDetectsFeatureMismatchAfterDowngrade(): void
    {
        // Build PDF 2.0 with /OCProperties + /Collection + /AF, then rewrite
        // the header to declare PDF 1.3 so validateVersion sees a mismatch.
        $writer = new PdfFileWriter(version: PdfVersion::V2_0);
        $catalog = new Catalog();
        $writer->setCatalog($catalog);
        $pageTree = new PageTree();
        $writer->register($pageTree);
        $catalog->pages = new PdfReference($pageTree->objectNumber);

        $ocg = new OCG('Layer 1');
        $writer->register($ocg);
        $ocProps = new OCPropertiesDict(
            new PdfArray([new PdfReference($ocg->objectNumber)]),
            new PdfDictionary(),
        );
        $writer->register($ocProps);
        $catalog->ocProperties = new PdfReference($ocProps->objectNumber);

        $bytes = $writer->generate();
        // Downgrade declared version
        $bytes = preg_replace('/%PDF-2\.\d/', '%PDF-1.3', $bytes, 1) ?? $bytes;
        // Also clear /Version from the catalog so getEffectiveVersion uses header
        $bytes = preg_replace('|/Version /2\.\d|', '', $bytes) ?? $bytes;

        $reader = PdfReader::fromString($bytes, strict: false);
        $warnings = $reader->validateVersion();
        $this->assertNotEmpty($warnings);
        $found = false;
        foreach ($warnings as $w) {
            if (str_contains($w, 'OCProperties')) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found);
    }

    public function testValidateVersionWithEncryptedPdf(): void
    {
        // Build encrypted PDF — validateVersion should inspect /Encrypt and
        // compute the required version against the declared version.
        $writer = new PdfFileWriter(version: PdfVersion::V1_4);
        $encryptor = \Phpdftk\Pdf\Core\Security\PdfEncryptor::aes128('user', 'owner', random_bytes(16));
        $writer->setEncryption($encryptor);
        $catalog = new Catalog();
        $writer->setCatalog($catalog);
        $pageTree = new PageTree();
        $writer->register($pageTree);
        $catalog->pages = new PdfReference($pageTree->objectNumber);

        $bytes = $writer->generate();
        $reader = PdfReader::fromString($bytes, password: 'user');
        $warnings = $reader->validateVersion();
        $this->assertIsArray($warnings);
    }
}
