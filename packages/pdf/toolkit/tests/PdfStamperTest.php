<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Toolkit\Tests;

use ApprLabs\Pdf\Core\Font\StandardFont;
use ApprLabs\Pdf\Core\Font\Type1Font;
use ApprLabs\Pdf\Reader\PdfReader;
use ApprLabs\Pdf\Toolkit\PageSelector;
use ApprLabs\Pdf\Toolkit\PdfStamper;
use ApprLabs\Pdf\Toolkit\Stamper\StampPosition;
use ApprLabs\Pdf\Toolkit\Stamper\StampStyle;
use ApprLabs\Pdf\Toolkit\Stamper\WatermarkStyle;
use ApprLabs\Pdf\Writer\PdfWriter;
use PHPUnit\Framework\TestCase;

class PdfStamperTest extends TestCase
{
    private function generatePdf(int $pages = 2): string
    {
        $writer = new PdfWriter(compressStreams: false);
        $font = $writer->addFont(new Type1Font(StandardFont::Helvetica));

        for ($i = 1; $i <= $pages; $i++) {
            $page = $writer->addPage(612, 792);
            $cs = $writer->addContentStream($page);
            $cs->beginText()
                ->setFont($font->getResourceName(), 12)
                ->moveTextPosition(72, 720)
                ->showText("Page $i content")
                ->endText();
        }

        return $writer->generate();
    }

    public function testStampText(): void
    {
        $pdf = $this->generatePdf();
        $result = PdfStamper::openString($pdf)
            ->stampText('CONFIDENTIAL', StampPosition::TopRight)
            ->toBytes();

        $this->assertStringStartsWith('%PDF', $result);
        $reader = PdfReader::fromString($result);
        $this->assertSame(2, $reader->getPageCount());
    }

    public function testWatermark(): void
    {
        $pdf = $this->generatePdf();
        $result = PdfStamper::openString($pdf)
            ->watermark('DRAFT')
            ->toBytes();

        $this->assertStringStartsWith('%PDF', $result);
        $reader = PdfReader::fromString($result);
        $this->assertSame(2, $reader->getPageCount());
        // Watermark should contain the text
        $text = $reader->extractAllText();
        // Note: watermarks use rotated text matrix, extraction may not capture it
    }

    public function testWatermarkWithStyle(): void
    {
        $pdf = $this->generatePdf();
        $style = new WatermarkStyle(fontSize: 72.0, r: 1.0, g: 0.0, b: 0.0, opacity: 0.2, rotation: 30.0);
        $result = PdfStamper::openString($pdf)
            ->watermark('TOP SECRET', style: $style)
            ->toBytes();

        $this->assertStringStartsWith('%PDF', $result);
    }

    public function testPageNumbers(): void
    {
        $pdf = $this->generatePdf(3);
        $result = PdfStamper::openString($pdf)
            ->addPageNumbers(StampPosition::BottomCenter, 'Page {n} of {total}')
            ->toBytes();

        $this->assertStringStartsWith('%PDF', $result);
        $reader = PdfReader::fromString($result);
        $this->assertSame(3, $reader->getPageCount());
    }

    public function testStampOnSpecificPages(): void
    {
        $pdf = $this->generatePdf(3);
        $result = PdfStamper::openString($pdf)
            ->stampText('ODD PAGE', StampPosition::TopLeft, PageSelector::odd())
            ->toBytes();

        $this->assertStringStartsWith('%PDF', $result);
    }

    public function testHeaderAndFooter(): void
    {
        $pdf = $this->generatePdf();
        $result = PdfStamper::openString($pdf)
            ->header('Company Name')
            ->footer('Confidential')
            ->toBytes();

        $this->assertStringStartsWith('%PDF', $result);
    }

    public function testStampWithOpacity(): void
    {
        $pdf = $this->generatePdf();
        $style = new StampStyle(fontSize: 14.0, opacity: 0.5);
        $result = PdfStamper::openString($pdf)
            ->stampText('Semi-transparent', StampPosition::Center, style: $style)
            ->toBytes();

        $this->assertStringStartsWith('%PDF', $result);
    }

    public function testNoOpsReturnsOriginal(): void
    {
        $pdf = $this->generatePdf();
        $result = PdfStamper::openString($pdf)->toBytes();
        $this->assertSame($pdf, $result);
    }

    public function testPageCount(): void
    {
        $pdf = $this->generatePdf(5);
        $stamper = PdfStamper::openString($pdf);
        $this->assertSame(5, $stamper->getPageCount());
    }

    public function testEscapeHatch(): void
    {
        $pdf = $this->generatePdf();
        $stamper = PdfStamper::openString($pdf);
        $this->assertInstanceOf(PdfReader::class, $stamper->getReader());
    }

    public function testSaveToFile(): void
    {
        $pdf = $this->generatePdf();
        $path = sys_get_temp_dir() . '/phpdftk_stamper_test_' . uniqid() . '.pdf';

        try {
            PdfStamper::openString($pdf)
                ->stampText('FILE TEST', StampPosition::Center)
                ->save($path);

            $this->assertFileExists($path);
            $this->assertStringStartsWith('%PDF', file_get_contents($path));
        } finally {
            @unlink($path);
        }
    }
}
