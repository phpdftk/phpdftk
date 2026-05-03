<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Toolkit\Tests;

use Phpdftk\Pdf\Core\Font\StandardFont;
use Phpdftk\Pdf\Core\Font\Type1Font;
use Phpdftk\Pdf\Reader\TextSpan;
use Phpdftk\Pdf\Toolkit\TextExtractor;
use Phpdftk\Pdf\Writer\PdfWriter;
use PHPUnit\Framework\TestCase;

class TextExtractorPositionedTest extends TestCase
{
    private function generateSimplePdf(): string
    {
        $writer = new PdfWriter(compressStreams: false);
        $page1 = $writer->addPage(612, 792);
        $page2 = $writer->addPage(612, 792);
        $font = $writer->addFont(new Type1Font(StandardFont::Helvetica));

        $cs1 = $writer->addContentStream($page1);
        $cs1->beginText()
            ->setFont($font->getResourceName(), 12)
            ->moveTextPosition(72, 720)
            ->showText('Hello World from page one')
            ->endText();

        $cs2 = $writer->addContentStream($page2);
        $cs2->beginText()
            ->setFont($font->getResourceName(), 14)
            ->moveTextPosition(100, 600)
            ->showText('Goodbye World from page two')
            ->endText();

        return $writer->generate();
    }

    public function testPageWithPositions(): void
    {
        $pdf = $this->generateSimplePdf();
        $extractor = TextExtractor::openString($pdf);

        $spans = $extractor->pageWithPositions(1);
        $this->assertNotEmpty($spans);
        $this->assertInstanceOf(TextSpan::class, $spans[0]);
        $this->assertSame('Hello World from page one', $spans[0]->text);
        $this->assertEqualsWithDelta(72.0, $spans[0]->x, 0.5);
        $this->assertEqualsWithDelta(720.0, $spans[0]->y, 0.5);
        $this->assertEqualsWithDelta(12.0, $spans[0]->fontSize, 0.1);
    }

    public function testPageWithPositionsSecondPage(): void
    {
        $pdf = $this->generateSimplePdf();
        $extractor = TextExtractor::openString($pdf);

        $spans = $extractor->pageWithPositions(2);
        $this->assertNotEmpty($spans);
        $this->assertSame('Goodbye World from page two', $spans[0]->text);
        $this->assertEqualsWithDelta(100.0, $spans[0]->x, 0.5);
        $this->assertEqualsWithDelta(600.0, $spans[0]->y, 0.5);
        $this->assertEqualsWithDelta(14.0, $spans[0]->fontSize, 0.1);
    }

    public function testAllPagesWithPositions(): void
    {
        $pdf = $this->generateSimplePdf();
        $extractor = TextExtractor::openString($pdf);

        $allSpans = $extractor->allPagesWithPositions();

        // Keys are 1-based
        $this->assertArrayHasKey(1, $allSpans);
        $this->assertArrayHasKey(2, $allSpans);
        $this->assertNotEmpty($allSpans[1]);
        $this->assertNotEmpty($allSpans[2]);

        $this->assertStringContainsString('Hello', $allSpans[1][0]->text);
        $this->assertStringContainsString('Goodbye', $allSpans[2][0]->text);
    }

    public function testEmptyPageReturnsEmptyArray(): void
    {
        $writer = new PdfWriter(compressStreams: false);
        $writer->addPage(612, 792); // empty page

        $pdf = $writer->generate();
        $extractor = TextExtractor::openString($pdf);

        $spans = $extractor->pageWithPositions(1);
        $this->assertSame([], $spans);
    }

    public function testOutOfRangePageThrows(): void
    {
        $pdf = $this->generateSimplePdf();
        $extractor = TextExtractor::openString($pdf);

        $this->expectException(\OutOfRangeException::class);
        $extractor->pageWithPositions(99);
    }

    public function testSpanWidthIsPositive(): void
    {
        $pdf = $this->generateSimplePdf();
        $extractor = TextExtractor::openString($pdf);

        foreach ($extractor->pageWithPositions(1) as $span) {
            $this->assertGreaterThan(0, $span->width);
            $this->assertGreaterThan(0, $span->height);
        }
    }

    public function testPositionedExtractionWithSampleFile(): void
    {
        $sampleDir = dirname(__DIR__, 4) . '/docs/sample-pdfs';
        $path = $sampleDir . '/simple_text.pdf';
        if (!file_exists($path)) {
            $this->markTestSkipped('simple_text.pdf not found');
        }

        $extractor = TextExtractor::open($path);
        $spans = $extractor->pageWithPositions(1);

        $this->assertNotEmpty($spans);
        foreach ($spans as $span) {
            $this->assertInstanceOf(TextSpan::class, $span);
            $this->assertNotSame('', $span->text);
            $this->assertTrue(is_finite($span->x));
            $this->assertTrue(is_finite($span->y));
        }
    }
}
