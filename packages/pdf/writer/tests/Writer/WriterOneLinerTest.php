<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Writer\Tests\Writer;

use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Writer\DashPattern;
use Phpdftk\Pdf\Writer\Pdf;
use Phpdftk\Pdf\Writer\PdfWriter;
use Phpdftk\Pdf\Writer\Theme;
use PHPUnit\Framework\TestCase;

/**
 * One-liner accessor coverage for Writer facade methods.
 */
class WriterOneLinerTest extends TestCase
{
    public function testDashPatternToOperatorArgs(): void
    {
        $dp = DashPattern::dashed(4, 2);
        $args = $dp->toOperatorArgs();
        $this->assertSame([4.0, 2.0], $args[0]);
        $this->assertSame(0.0, $args[1]);
    }

    public function testDashPatternFactories(): void
    {
        $this->assertSame([], DashPattern::solid()->pattern);
        $this->assertSame([1.0, 2.0], DashPattern::dotted()->pattern);
        $this->assertSame([6.0, 2.0, 1.0, 2.0], DashPattern::dashDot()->pattern);
    }

    public function testPdfSetThemeAndGetTheme(): void
    {
        $pdf = new Pdf();
        $theme = new Theme();
        $pdf->setTheme($theme);
        $this->assertSame($theme, $pdf->getTheme());
    }

    public function testPdfGetPdfVersion(): void
    {
        $pdf = new Pdf();
        $this->assertInstanceOf(PdfVersion::class, $pdf->getPdfVersion());
    }

    public function testPdfWriterGetPdfVersion(): void
    {
        $writer = new PdfWriter();
        $this->assertInstanceOf(PdfVersion::class, $writer->getPdfVersion());
    }

    public function testPdfWriterSetStrictVersionMode(): void
    {
        $writer = new PdfWriter();
        $writer->setStrictVersionMode(true);
        $writer->setStrictVersionMode(false);
        $this->assertStringStartsWith('%PDF-', $writer->generate());
    }

    public function testPdfWriterSetCeilingVersion(): void
    {
        $writer = new PdfWriter();
        $writer->setCeilingVersion(PdfVersion::V1_7);
        $this->assertStringStartsWith('%PDF-', $writer->generate());
    }

    public function testPdfWriterSetTsaClient(): void
    {
        $writer = new PdfWriter();
        $tsa = new \Phpdftk\Pdf\Core\Interactive\Signature\TsaClient('https://example.invalid/tsa');
        $writer->setTsaClient($tsa);
        $this->assertStringStartsWith('%PDF-', $writer->generate());
    }

    public function testPdfWriterSetTimestamperWithDocTimeStamp(): void
    {
        $writer = new PdfWriter();
        $docTs = new \Phpdftk\Pdf\Core\Interactive\Signature\SignatureValue(
            filter: 'Adobe.PPKLite',
            subFilter: 'ETSI.RFC3161',
        );
        $tsa = new \Phpdftk\Pdf\Core\Interactive\Signature\TsaClient('https://example.invalid/tsa');
        $writer->setTimestamper($docTs, $tsa);
        // setTimestamper installs placeholder /Contents and /ByteRange
        $this->assertNotNull($docTs->contents);
    }

    public function testPdfWriterDeprecationHandlerAndStrictMode(): void
    {
        $writer = new PdfWriter();
        $called = false;
        $writer->setDeprecationHandler(function () use (&$called) {
            $called = true;
        });
        $writer->setStrictDeprecation(true);
        $writer->setStrictDeprecation(false);
        // Generate a basic PDF — no deprecated features used, handler not called.
        $writer->generate();
        $this->assertFalse($called);
    }

    public function testPdfWriterGetVersionWarnings(): void
    {
        $writer = new PdfWriter();
        $this->assertIsArray($writer->getVersionWarnings());
    }

    public function testIncrementalWriterSetDeprecationHandler(): void
    {
        $base = new \Phpdftk\Pdf\Core\File\PdfFileWriter();
        $base->setCatalog(new \Phpdftk\Pdf\Core\Document\Catalog());
        $bytes = $base->generate();

        $inc = new \Phpdftk\Pdf\Core\File\IncrementalWriter(
            originalPdf: $bytes,
            originalSize: 10,
            originalXrefOffset: 100,
            rootRef: new \Phpdftk\Pdf\Core\PdfReference(1),
        );
        $called = false;
        $inc->setDeprecationHandler(function () use (&$called) {
            $called = true;
        });
        $this->assertFalse($called);
    }
}
