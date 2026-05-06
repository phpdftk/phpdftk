<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Tests\File;

use Phpdftk\Pdf\Core\Annotation\RedactAnnotation;
use Phpdftk\Pdf\Core\Document\Catalog;
use Phpdftk\Pdf\Core\Document\PageTree;
use Phpdftk\Pdf\Core\Document\ViewerPreferences;
use Phpdftk\Pdf\Core\File\CeilingVersionException;
use Phpdftk\Pdf\Core\File\PdfFileWriter;
use Phpdftk\Pdf\Core\File\VersionRequirementResolver;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Core\Security\PdfEncryptor;
use PHPUnit\Framework\TestCase;

class CeilingVersionTest extends TestCase
{
    protected function setUp(): void
    {
        VersionRequirementResolver::clearCache();
    }

    private function createWriter(PdfVersion $ceiling): PdfFileWriter
    {
        $writer = new PdfFileWriter();
        $writer->setCeilingVersion($ceiling);
        $catalog = new Catalog();
        $writer->setCatalog($catalog);
        $pageTree = new PageTree();
        $writer->register($pageTree);
        $catalog->pages = new PdfReference($pageTree->objectNumber);
        return $writer;
    }

    public function testPropertyStripping(): void
    {
        $writer = $this->createWriter(PdfVersion::V1_7);

        // ViewerPreferences.$enforce requires 2.0 — should be stripped
        $vp = new ViewerPreferences();
        $vp->enforce = new PdfArray([new PdfName('PrintScaling')]);
        $vp->displayDocTitle = true; // 1.0 feature, should survive
        $writer->register($vp);

        // enforce should have been nullified
        $this->assertNull($vp->enforce);
        $this->assertTrue($vp->displayDocTitle);

        // Version should remain at ceiling
        $this->assertSame(PdfVersion::V1_7, $writer->getPdfVersion());

        // Should have a warning about stripping
        $warnings = $writer->getVersionWarnings();
        $strippedWarnings = array_filter($warnings, fn($w) => str_contains($w, 'Stripped'));
        $this->assertNotEmpty($strippedWarnings);
    }

    public function testClassLevelRefusal(): void
    {
        $writer = $this->createWriter(PdfVersion::V1_4);

        $this->expectException(CeilingVersionException::class);
        $this->expectExceptionMessageMatches('/RedactAnnotation.*requires PDF 1\.5.*ceiling.*1\.4/');

        $rect = new PdfArray([new PdfNumber(0), new PdfNumber(0), new PdfNumber(100), new PdfNumber(100)]);
        $redact = new RedactAnnotation($rect);
        $writer->register($redact);
    }

    public function testXrefStreamDowngrade(): void
    {
        $writer = new PdfFileWriter(useXRefStream: true);
        $writer->setCeilingVersion(PdfVersion::V1_4);
        $catalog = new Catalog();
        $writer->setCatalog($catalog);
        $pageTree = new PageTree();
        $writer->register($pageTree);
        $catalog->pages = new PdfReference($pageTree->objectNumber);

        $pdf = $writer->generate();

        // Should have downgraded to classic xref
        $this->assertStringStartsWith('%PDF-1.4', $pdf);
        $this->assertStringContainsString('xref', $pdf);
        // Classic xref — no /Type /XRef stream
        $this->assertStringNotContainsString('/Type /XRef', $pdf);

        $warnings = $writer->getVersionWarnings();
        $downgradeWarnings = array_filter($warnings, fn($w) => str_contains($w, 'Downgraded'));
        $this->assertNotEmpty($downgradeWarnings);
    }

    public function testEncryptionRefusal(): void
    {
        $writer = $this->createWriter(PdfVersion::V1_4);
        $fileId = md5('test', true);

        $this->expectException(CeilingVersionException::class);
        $this->expectExceptionMessageMatches('/PdfEncryptor.*1\.6.*ceiling.*1\.4/');

        $enc = PdfEncryptor::aes128('user', 'owner', $fileId);
        $writer->setEncryption($enc);
    }

    public function testCeilingAllowsCompatibleFeatures(): void
    {
        $writer = $this->createWriter(PdfVersion::V1_7);

        // ViewerPreferences without 2.0 properties — should work fine
        $vp = new ViewerPreferences();
        $vp->displayDocTitle = true;
        $writer->register($vp);

        $this->assertSame(PdfVersion::V1_7, $writer->getPdfVersion());
    }

    public function testEndToEndCeilingPdf(): void
    {
        $writer = $this->createWriter(PdfVersion::V1_4);

        $pdf = $writer->generate();

        $this->assertStringStartsWith('%PDF-1.4', $pdf);
        // Should NOT have catalog /Version (only set for > 1.4)
        $this->assertStringNotContainsString('/Version', $pdf);
    }

    public function testCeilingVersionSetsDocumentVersion(): void
    {
        $writer = new PdfFileWriter();
        $writer->setCeilingVersion(PdfVersion::V1_3);

        $this->assertSame(PdfVersion::V1_3, $writer->getPdfVersion());
    }

    public function testCeilingDisablesStrictMode(): void
    {
        $writer = new PdfFileWriter();
        $writer->setStrictVersionMode(true);
        $writer->setCeilingVersion(PdfVersion::V1_7);

        // Ceiling should have cleared strict mode — register a 1.3 object should work
        // (strict mode would have no effect since ceiling controls version)
        $catalog = new Catalog();
        $writer->setCatalog($catalog);
        $pageTree = new PageTree();
        $writer->register($pageTree);
        $catalog->pages = new PdfReference($pageTree->objectNumber);

        $this->assertSame(PdfVersion::V1_7, $writer->getPdfVersion());
    }

    public function testCeilingRejectsRemovedFeature(): void
    {
        $writer = $this->createWriter(PdfVersion::V2_0);

        $this->expectException(\Phpdftk\Pdf\Core\File\DeprecatedFeatureException::class);
        $this->expectExceptionMessageMatches('/Movie.*removed in PDF 2\.0/');

        $movie = new \Phpdftk\Pdf\Core\Multimedia\Movie(
            new \Phpdftk\Pdf\Core\FileSpec\FileSpec('test.pdf'),
        );
        $writer->register($movie);
    }

    public function testCeilingAllowsDeprecatedBelowRemoval(): void
    {
        $writer = $this->createWriter(PdfVersion::V1_7);

        // Movie removed in 2.0, ceiling is 1.7 — should be allowed
        $movie = new \Phpdftk\Pdf\Core\Multimedia\Movie(
            new \Phpdftk\Pdf\Core\FileSpec\FileSpec('test.pdf'),
        );
        $writer->register($movie);

        $this->assertSame(PdfVersion::V1_7, $writer->getPdfVersion());
    }

    public function testCeilingRefusesNewlyAnnotatedClass(): void
    {
        $writer = $this->createWriter(PdfVersion::V1_1);

        $this->expectException(CeilingVersionException::class);
        $this->expectExceptionMessageMatches('/Movie.*requires PDF 1\.2.*ceiling.*1\.1/');

        $movie = new \Phpdftk\Pdf\Core\Multimedia\Movie(
            new \Phpdftk\Pdf\Core\FileSpec\FileSpec('test.pdf'),
        );
        $writer->register($movie);
    }
}
