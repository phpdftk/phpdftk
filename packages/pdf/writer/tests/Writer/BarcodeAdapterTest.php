<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Writer\Tests;

use Phpdftk\Barcode\BarcodeOptions;
use Phpdftk\Barcode\Symbology;
use Phpdftk\Pdf\Writer\Pdf;
use Phpdftk\Pdf\Writer\PdfDoc;
use Phpdftk\Tests\Support\QpdfValidationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group("qpdf")]
class BarcodeAdapterTest extends TestCase
{
    use QpdfValidationTrait;

    public function testPdfDocCreateBarcodeReturnsFormXObject(): void
    {
        $doc = new PdfDoc(compressStreams: false);
        $template = $doc->createBarcode(Symbology::Code128, 'HELLO');
        self::assertGreaterThan(0, $template->objectNumber);

        $page = $doc->addPage();
        $page->drawTemplate($template, 72, 720);
        $bytes = $doc->writer()->generate();
        self::assertStringContainsString('/Subtype /Form', $bytes);
        self::assertMatchesRegularExpression('/\/Tpl\d+ Do/', $bytes);
    }

    public function testWriterPageDrawBarcodeEmitsFillRectangles(): void
    {
        $doc = new PdfDoc(compressStreams: false);
        $page = $doc->addPage();
        $page->drawBarcode(Symbology::Code128, 'AB12', 72.0, 720.0);
        $bytes = $doc->writer()->generate();
        // Barcode rendering emits rectangle + fill operators.
        self::assertMatchesRegularExpression('/\d+(?:\.\d+)? \d+ \d+(?:\.\d+)? \d+(?:\.\d+)? re/', $bytes);
        self::assertStringContainsString("\nf\n", $bytes);
    }

    public function testPdfAddBarcodeFlowsAtCursor(): void
    {
        $pdf = new Pdf(compressStreams: false);
        $pdf->addHeading('Receipt', 1);
        $pdf->addBarcode(Symbology::Code128, 'ORDER-12345');
        $bytes = $pdf->toBytes();
        // Heading and barcode both render.
        self::assertStringContainsString('(Receipt)', $bytes);
        self::assertMatchesRegularExpression('/\d+(?:\.\d+)? \d+ \d+(?:\.\d+)? \d+(?:\.\d+)? re/', $bytes);
    }

    public function testPdfAddBarcodeRespectsAlignmentAndCustomOptions(): void
    {
        $pdf = new Pdf(compressStreams: false);
        $pdf->addBarcode(
            Symbology::Code128,
            '12345',
            new BarcodeOptions(moduleWidth: 1.5, height: 40.0, quietZoneModules: 5),
            align: \Phpdftk\Pdf\Writer\Alignment::Center,
        );
        $bytes = $pdf->toBytes();
        self::assertStringContainsString("\nf\n", $bytes);
    }

    public function testEmptyDataBubblesUpAsInvalidArgument(): void
    {
        $doc = new PdfDoc();
        $this->expectException(\InvalidArgumentException::class);
        $doc->createBarcode(Symbology::Code128, '');
    }

    public function testDataMatrixAdapterEmits2DGrid(): void
    {
        $doc = new PdfDoc(compressStreams: false);
        $page = $doc->addPage();
        $page->drawBarcode(Symbology::DataMatrix, 'ORDER 12345', 72.0, 600.0);
        $bytes = $doc->writer()->generate();
        // Multiple filled cells produce many re ops.
        self::assertGreaterThan(5, substr_count($bytes, ' re'));
        self::assertStringContainsString("\nf\n", $bytes);
    }

    public function testQrCodeAdapterPlacesA2dMatrix(): void
    {
        $doc = new PdfDoc(compressStreams: false);
        $page = $doc->addPage();
        $page->drawBarcode(Symbology::QR, 'https://phpdftk.dev/', 72.0, 600.0);
        $bytes = $doc->writer()->generate();
        // QR fills produce many small re/f operators.
        self::assertGreaterThan(5, substr_count($bytes, ' re'));
        self::assertStringContainsString("\nf\n", $bytes);
    }
}
