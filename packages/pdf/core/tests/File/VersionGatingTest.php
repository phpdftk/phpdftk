<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Tests\File;

use Phpdftk\Pdf\Core\Document\Catalog;
use Phpdftk\Pdf\Core\Document\DPartRoot;
use Phpdftk\Pdf\Core\Document\PageTree;
use Phpdftk\Pdf\Core\Document\ViewerPreferences;
use Phpdftk\Pdf\Core\File\PdfFileWriter;
use Phpdftk\Pdf\Core\File\VersionRequirementException;
use Phpdftk\Pdf\Core\File\VersionRequirementResolver;
use Phpdftk\Pdf\Core\FileSpec\FileSpec;
use Phpdftk\Pdf\Core\Multimedia\Movie;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Core\Annotation\RedactAnnotation;
use Phpdftk\Pdf\Core\Document\StandardStructureType;
use Phpdftk\Pdf\Core\Document\StructElem;
use PHPUnit\Framework\TestCase;

class VersionGatingTest extends TestCase
{
    protected function setUp(): void
    {
        VersionRequirementResolver::clearCache();
    }

    private function makeRect(): PdfArray
    {
        return new PdfArray([new PdfNumber(0), new PdfNumber(0), new PdfNumber(100), new PdfNumber(100)]);
    }

    private function createWriter(PdfVersion $version = PdfVersion::V1_7): PdfFileWriter
    {
        $writer = new PdfFileWriter(version: $version);
        $catalog = new Catalog();
        $writer->setCatalog($catalog);
        $pageTree = new PageTree();
        $writer->register($pageTree);
        $catalog->pages = new PdfReference($pageTree->objectNumber);
        return $writer;
    }

    public function testDefaultVersion(): void
    {
        $writer = new PdfFileWriter();
        $this->assertSame('1.7', $writer->getVersion());
        $this->assertSame(PdfVersion::V1_7, $writer->getPdfVersion());
    }

    public function testStringVersionBackwardCompat(): void
    {
        $writer = new PdfFileWriter(version: '1.5');
        $this->assertSame('1.5', $writer->getVersion());
        $this->assertSame(PdfVersion::V1_5, $writer->getPdfVersion());
    }

    public function testSetVersionWithEnum(): void
    {
        $writer = new PdfFileWriter();
        $writer->setVersion(PdfVersion::V2_0);
        $this->assertSame('2.0', $writer->getVersion());
    }

    public function testSetVersionWithString(): void
    {
        $writer = new PdfFileWriter();
        $writer->setVersion('1.5');
        $this->assertSame('1.5', $writer->getVersion());
    }

    public function testAutoBumpOnRegister(): void
    {
        $writer = $this->createWriter(PdfVersion::V1_4);

        $redact = new RedactAnnotation($this->makeRect());
        $writer->register($redact);

        $this->assertSame(PdfVersion::V1_5, $writer->getPdfVersion());
        $warnings = $writer->getVersionWarnings();
        $this->assertNotEmpty($warnings);
        $this->assertStringContainsString('Auto-bumped', $warnings[0]);
        $this->assertStringContainsString('1.5', $warnings[0]);
    }

    public function testAutoBumpTo20(): void
    {
        $writer = $this->createWriter(PdfVersion::V1_7);

        $dPartRoot = new DPartRoot(new PdfReference(1));
        $writer->register($dPartRoot);

        $this->assertSame(PdfVersion::V2_0, $writer->getPdfVersion());
    }

    public function testAutoBumpFromPropertyLevel(): void
    {
        $writer = $this->createWriter(PdfVersion::V1_7);

        $vp = new ViewerPreferences();
        $vp->enforce = new PdfArray([new PdfName('PrintScaling')]);
        $writer->register($vp);

        $this->assertSame(PdfVersion::V2_0, $writer->getPdfVersion());
    }

    public function testNoBumpWhenPropertyNull(): void
    {
        $writer = $this->createWriter(PdfVersion::V1_7);

        $vp = new ViewerPreferences();
        // enforce is null — should not bump
        $writer->register($vp);

        $this->assertSame(PdfVersion::V1_7, $writer->getPdfVersion());
    }

    public function testStrictModeThrows(): void
    {
        $writer = $this->createWriter(PdfVersion::V1_4);
        $writer->setStrictVersionMode(true);

        $this->expectException(VersionRequirementException::class);
        $this->expectExceptionMessageMatches('/requires PDF 1\.5/');

        $redact = new RedactAnnotation($this->makeRect());
        $writer->register($redact);
    }

    public function testDeprecationWarningCollected(): void
    {
        $writer = $this->createWriter(PdfVersion::V1_7);

        $movie = new Movie(new FileSpec('test.pdf'));
        $writer->register($movie);

        $warnings = $writer->getVersionWarnings();
        $this->assertNotEmpty($warnings);
        $this->assertStringContainsString('deprecated since PDF 2.0', $warnings[0]);
        $this->assertStringContainsString('RichMediaAnnotation', $warnings[0]);
    }

    public function testDeprecationHandlerCalled(): void
    {
        $writer = $this->createWriter(PdfVersion::V1_7);
        $called = false;
        $writer->setDeprecationHandler(function (string $msg) use (&$called) {
            $called = true;
            $this->assertStringContainsString('deprecated', $msg);
        });

        $movie = new Movie(new FileSpec('test.pdf'));
        $writer->register($movie);

        $this->assertTrue($called);
    }

    public function testCatalogVersionSyncInGeneratedPdf(): void
    {
        $writer = $this->createWriter(PdfVersion::V2_0);

        $pdf = $writer->generate();

        $this->assertStringStartsWith('%PDF-2.0', $pdf);
        $this->assertStringContainsString('/Version /2.0', $pdf);
    }

    public function testCatalogVersionNotSetForV14(): void
    {
        $writer = $this->createWriter(PdfVersion::V1_4);

        $pdf = $writer->generate();

        $this->assertStringStartsWith('%PDF-1.4', $pdf);
        // Catalog should NOT contain /Version for 1.4
        $this->assertStringNotContainsString('/Version /1.4', $pdf);
    }

    public function testXrefStreamAutoBumps(): void
    {
        $writer = new PdfFileWriter(useXRefStream: true, version: PdfVersion::V1_4);
        $catalog = new Catalog();
        $writer->setCatalog($catalog);
        $pageTree = new PageTree();
        $writer->register($pageTree);
        $catalog->pages = new PdfReference($pageTree->objectNumber);

        $pdf = $writer->generate();

        // Even though we set 1.4, xref stream forces 1.5+
        $this->assertStringStartsWith('%PDF-1.5', $pdf);
    }

    public function testStructElemPdf20TypeAutoBumps(): void
    {
        $writer = $this->createWriter(PdfVersion::V1_7);

        $elem = new StructElem(StandardStructureType::ASIDE);
        $writer->register($elem);

        $this->assertSame(PdfVersion::V2_0, $writer->getPdfVersion());
    }

    public function testStructElemPrePdf20TypeNoBump(): void
    {
        $writer = $this->createWriter(PdfVersion::V1_7);

        $elem = new StructElem(StandardStructureType::P);
        $writer->register($elem);

        $this->assertSame(PdfVersion::V1_7, $writer->getPdfVersion());
    }

    public function testNoBumpWhenVersionAlreadySufficient(): void
    {
        $writer = $this->createWriter(PdfVersion::V1_7);

        // RedactAnnotation requires 1.5, but writer is already at 1.7
        $redact = new RedactAnnotation($this->makeRect());
        $writer->register($redact);

        $this->assertSame(PdfVersion::V1_7, $writer->getPdfVersion());
        // No auto-bump warning
        $autoBumpWarnings = array_filter(
            $writer->getVersionWarnings(),
            fn($w) => str_contains($w, 'Auto-bumped')
        );
        $this->assertEmpty($autoBumpWarnings);
    }

    // --- Strict deprecation enforcement tests ---

    public function testStrictDeprecationThrowsForRemovedFeature(): void
    {
        $writer = $this->createWriter(PdfVersion::V2_0);
        $writer->setStrictDeprecation(true);

        $this->expectException(\Phpdftk\Pdf\Core\File\DeprecatedFeatureException::class);
        $this->expectExceptionMessageMatches('/Movie.*removed in PDF 2\.0/');

        $movie = new Movie(new FileSpec('test.pdf'));
        $writer->register($movie);
    }

    public function testStrictDeprecationAllowsBelowRemovalVersion(): void
    {
        $writer = $this->createWriter(PdfVersion::V1_7);
        $writer->setStrictDeprecation(true);

        // Movie removed in 2.0, but we're targeting 1.7 — should be fine
        $movie = new Movie(new FileSpec('test.pdf'));
        $writer->register($movie);

        // Should still get a deprecation warning
        $warnings = $writer->getVersionWarnings();
        $this->assertNotEmpty($warnings);
        $this->assertStringContainsString('deprecated', $warnings[0]);
    }

    public function testNonStrictDeprecationStillWarns(): void
    {
        $writer = $this->createWriter(PdfVersion::V2_0);
        // strictDeprecation is false by default

        $movie = new Movie(new FileSpec('test.pdf'));
        $writer->register($movie);

        // Should warn but not throw
        $warnings = $writer->getVersionWarnings();
        $this->assertNotEmpty($warnings);
        $this->assertStringContainsString('deprecated since PDF 2.0', $warnings[0]);
    }

    public function testPostScriptXObjectRemovedInThrowsAtVersion2(): void
    {
        $writer = $this->createWriter(PdfVersion::V2_0);
        $writer->setStrictDeprecation(true);

        // PostScriptXObject has removedIn=2.0 — should throw in strict mode
        $ps = new \Phpdftk\Pdf\Core\Graphics\XObject\PostScriptXObject('');

        $this->expectException(\Phpdftk\Pdf\Core\File\DeprecatedFeatureException::class);
        $writer->register($ps);
    }

    public function testPostScriptXObjectWarnsWithoutStrictDeprecation(): void
    {
        $writer = $this->createWriter(PdfVersion::V2_0);

        // Without strict deprecation, should warn but not throw
        $ps = new \Phpdftk\Pdf\Core\Graphics\XObject\PostScriptXObject('');
        $writer->register($ps);

        $warnings = $writer->getVersionWarnings();
        $depWarnings = array_filter($warnings, fn($w) => str_contains($w, 'deprecated'));
        $this->assertNotEmpty($depWarnings);
    }
}
