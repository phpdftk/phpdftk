<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Reader\Tests\Integration;

use Phpdftk\Pdf\Core\Content\ContentStream;
use Phpdftk\Pdf\Core\Content\Resources;
use Phpdftk\Pdf\Core\Font\StandardFont;
use Phpdftk\Pdf\Core\Font\Type1Font;
use Phpdftk\Pdf\Core\Graphics\XObject\FormXObject;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Reader\PdfReader;
use Phpdftk\Pdf\Reader\TextSpan;
use Phpdftk\Pdf\Writer\PdfWriter;
use PHPUnit\Framework\TestCase;

class PositionedTextExtractionTest extends TestCase
{
    private function samplePath(string $name): string
    {
        return __DIR__ . '/../../../../../docs/sample-pdfs/' . $name;
    }

    // -----------------------------------------------------------------------
    // Basic positioning tests
    // -----------------------------------------------------------------------

    public function testSimpleTextPosition(): void
    {
        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $font = $writer->addFont(new Type1Font(StandardFont::Helvetica));

        $cs = $writer->addContentStream($page);
        $cs->beginText()
            ->setFont($font->getResourceName(), 12)
            ->moveTextPosition(72, 720)
            ->showText('Hello World')
            ->endText();

        $bytes = $writer->generate();
        $reader = PdfReader::fromString($bytes);
        $spans = $reader->extractTextWithPositions(0);

        $this->assertNotEmpty($spans);
        $this->assertInstanceOf(TextSpan::class, $spans[0]);
        $this->assertSame('Hello World', $spans[0]->text);
        $this->assertEqualsWithDelta(72.0, $spans[0]->x, 0.5);
        $this->assertEqualsWithDelta(720.0, $spans[0]->y, 0.5);
        $this->assertEqualsWithDelta(12.0, $spans[0]->fontSize, 0.1);
        $this->assertGreaterThan(0, $spans[0]->width);
        $this->assertGreaterThan(0, $spans[0]->height);
    }

    public function testFontNameIsPreserved(): void
    {
        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $font = $writer->addFont(new Type1Font(StandardFont::Helvetica));

        $cs = $writer->addContentStream($page);
        $cs->beginText()
            ->setFont($font->getResourceName(), 12)
            ->moveTextPosition(72, 720)
            ->showText('Test')
            ->endText();

        $bytes = $writer->generate();
        $reader = PdfReader::fromString($bytes);
        $spans = $reader->extractTextWithPositions(0);

        $this->assertNotEmpty($spans);
        $this->assertSame($font->getResourceName(), $spans[0]->fontName);
    }

    public function testMultipleTextPositions(): void
    {
        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $font = $writer->addFont(new Type1Font(StandardFont::Helvetica));

        $cs = $writer->addContentStream($page);
        $cs->beginText()
            ->setFont($font->getResourceName(), 12)
            ->moveTextPosition(72, 720)
            ->showText('First line')
            ->moveTextPosition(0, -20)
            ->showText('Second line')
            ->endText();

        $bytes = $writer->generate();
        $reader = PdfReader::fromString($bytes);
        $spans = $reader->extractTextWithPositions(0);

        $this->assertCount(2, $spans);
        $this->assertSame('First line', $spans[0]->text);
        $this->assertSame('Second line', $spans[1]->text);

        // Second line should be lower (smaller y)
        $this->assertLessThan($spans[0]->y, $spans[1]->y);
        // Both start at same x
        $this->assertEqualsWithDelta($spans[0]->x, $spans[1]->x, 0.5);
    }

    public function testTextMatrixPositioning(): void
    {
        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $font = $writer->addFont(new Type1Font(StandardFont::Helvetica));

        $cs = $writer->addContentStream($page);
        $cs->beginText()
            ->setFont($font->getResourceName(), 14)
            ->setTextMatrix(1, 0, 0, 1, 100, 500)
            ->showText('Matrix positioned')
            ->endText();

        $bytes = $writer->generate();
        $reader = PdfReader::fromString($bytes);
        $spans = $reader->extractTextWithPositions(0);

        $this->assertNotEmpty($spans);
        $this->assertSame('Matrix positioned', $spans[0]->text);
        $this->assertEqualsWithDelta(100.0, $spans[0]->x, 0.5);
        $this->assertEqualsWithDelta(500.0, $spans[0]->y, 0.5);
        $this->assertEqualsWithDelta(14.0, $spans[0]->fontSize, 0.1);
    }

    // -----------------------------------------------------------------------
    // Width computation tests
    // -----------------------------------------------------------------------

    public function testSpanWidthReflectsTextLength(): void
    {
        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $font = $writer->addFont(new Type1Font(StandardFont::Helvetica));

        $cs = $writer->addContentStream($page);
        $cs->beginText()
            ->setFont($font->getResourceName(), 12)
            ->moveTextPosition(72, 720)
            ->showText('Hi')
            ->endText();
        $cs->beginText()
            ->setFont($font->getResourceName(), 12)
            ->moveTextPosition(72, 700)
            ->showText('Hello World from this longer string')
            ->endText();

        $bytes = $writer->generate();
        $reader = PdfReader::fromString($bytes);
        $spans = $reader->extractTextWithPositions(0);

        $this->assertCount(2, $spans);
        // Longer text should produce wider span
        $this->assertGreaterThan($spans[0]->width, $spans[1]->width);
    }

    public function testFontSizeAffectsWidth(): void
    {
        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $font = $writer->addFont(new Type1Font(StandardFont::Courier));

        $cs = $writer->addContentStream($page);
        // Same text at different sizes
        $cs->beginText()
            ->setFont($font->getResourceName(), 10)
            ->moveTextPosition(72, 720)
            ->showText('Test')
            ->endText();
        $cs->beginText()
            ->setFont($font->getResourceName(), 20)
            ->moveTextPosition(72, 700)
            ->showText('Test')
            ->endText();

        $bytes = $writer->generate();
        $reader = PdfReader::fromString($bytes);
        $spans = $reader->extractTextWithPositions(0);

        $this->assertCount(2, $spans);
        // 20pt text should be about twice as wide as 10pt
        $ratio = $spans[1]->width / $spans[0]->width;
        $this->assertEqualsWithDelta(2.0, $ratio, 0.1);
    }

    // -----------------------------------------------------------------------
    // Text advancement (cursor position after text)
    // -----------------------------------------------------------------------

    public function testTextAdvancementForConsecutiveStrings(): void
    {
        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $font = $writer->addFont(new Type1Font(StandardFont::Helvetica));

        $cs = $writer->addContentStream($page);
        $cs->beginText()
            ->setFont($font->getResourceName(), 12)
            ->moveTextPosition(72, 720)
            ->showText('ABC')
            ->showText('DEF')
            ->endText();

        $bytes = $writer->generate();
        $reader = PdfReader::fromString($bytes);
        $spans = $reader->extractTextWithPositions(0);

        $this->assertCount(2, $spans);
        $this->assertSame('ABC', $spans[0]->text);
        $this->assertSame('DEF', $spans[1]->text);
        // DEF should start where ABC ends
        $this->assertEqualsWithDelta(
            $spans[0]->x + $spans[0]->width,
            $spans[1]->x,
            1.0,
        );
    }

    // -----------------------------------------------------------------------
    // TJ array handling
    // -----------------------------------------------------------------------

    public function testTJArrayProducesSpans(): void
    {
        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $font = $writer->addFont(new Type1Font(StandardFont::Helvetica));

        $cs = $writer->addContentStream($page);
        $cs->beginText()
            ->setFont($font->getResourceName(), 12)
            ->moveTextPosition(72, 720)
            // TJ with kerning adjustments — small adjustments merge, large ones split
            ->showTextArray([
                'Ke',
                -20,
                'rned',
            ])
            ->endText();

        $bytes = $writer->generate();
        $reader = PdfReader::fromString($bytes);
        $spans = $reader->extractTextWithPositions(0);

        // Small kerning adjustment should keep text in one span
        $this->assertNotEmpty($spans);
        $text = implode('', array_map(fn(TextSpan $s) => $s->text, $spans));
        $this->assertSame('Kerned', $text);
    }

    public function testTJArrayWordSpaceSplitsSpans(): void
    {
        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $font = $writer->addFont(new Type1Font(StandardFont::Helvetica));

        $cs = $writer->addContentStream($page);
        $cs->beginText()
            ->setFont($font->getResourceName(), 12)
            ->moveTextPosition(72, 720)
            // Large positive number (>100) = word space → separate spans
            ->showTextArray([
                'Hello',
                200,
                'World',
            ])
            ->endText();

        $bytes = $writer->generate();
        $reader = PdfReader::fromString($bytes);
        $spans = $reader->extractTextWithPositions(0);

        $this->assertCount(2, $spans);
        $this->assertSame('Hello', $spans[0]->text);
        $this->assertSame('World', $spans[1]->text);
    }

    // -----------------------------------------------------------------------
    // Multi-page extraction
    // -----------------------------------------------------------------------

    public function testExtractAllPagesWithPositions(): void
    {
        $writer = new PdfWriter(compressStreams: false);
        $page1 = $writer->addPage(612, 792);
        $page2 = $writer->addPage(612, 792);
        $font = $writer->addFont(new Type1Font(StandardFont::Helvetica));

        $cs1 = $writer->addContentStream($page1);
        $cs1->beginText()
            ->setFont($font->getResourceName(), 12)
            ->moveTextPosition(72, 720)
            ->showText('Page One')
            ->endText();

        $cs2 = $writer->addContentStream($page2);
        $cs2->beginText()
            ->setFont($font->getResourceName(), 12)
            ->moveTextPosition(72, 720)
            ->showText('Page Two')
            ->endText();

        $bytes = $writer->generate();
        $reader = PdfReader::fromString($bytes);
        $allSpans = $reader->extractAllTextWithPositions();

        $this->assertArrayHasKey(0, $allSpans);
        $this->assertArrayHasKey(1, $allSpans);
        $this->assertNotEmpty($allSpans[0]);
        $this->assertNotEmpty($allSpans[1]);
        $this->assertSame('Page One', $allSpans[0][0]->text);
        $this->assertSame('Page Two', $allSpans[1][0]->text);
    }

    // -----------------------------------------------------------------------
    // Graphics state (CTM) tests
    // -----------------------------------------------------------------------

    public function testCTMTranslationAffectsPosition(): void
    {
        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $font = $writer->addFont(new Type1Font(StandardFont::Helvetica));

        $cs = $writer->addContentStream($page);
        // Apply CTM translation of (100, 50) before text
        $cs->concatMatrix(1, 0, 0, 1, 100, 50);
        $cs->beginText()
            ->setFont($font->getResourceName(), 12)
            ->moveTextPosition(10, 20)
            ->showText('Translated')
            ->endText();

        $bytes = $writer->generate();
        $reader = PdfReader::fromString($bytes);
        $spans = $reader->extractTextWithPositions(0);

        $this->assertNotEmpty($spans);
        // Position should be CTM translation + text position = (110, 70)
        $this->assertEqualsWithDelta(110.0, $spans[0]->x, 0.5);
        $this->assertEqualsWithDelta(70.0, $spans[0]->y, 0.5);
    }

    public function testGraphicsStateSaveRestore(): void
    {
        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $font = $writer->addFont(new Type1Font(StandardFont::Helvetica));

        $cs = $writer->addContentStream($page);
        // First text with CTM offset
        $cs->saveGraphicsState()
            ->concatMatrix(1, 0, 0, 1, 50, 0);
        $cs->beginText()
            ->setFont($font->getResourceName(), 12)
            ->moveTextPosition(10, 720)
            ->showText('Offset text')
            ->endText();
        $cs->restoreGraphicsState();

        // Second text without offset (CTM restored)
        $cs->beginText()
            ->setFont($font->getResourceName(), 12)
            ->moveTextPosition(10, 700)
            ->showText('Normal text')
            ->endText();

        $bytes = $writer->generate();
        $reader = PdfReader::fromString($bytes);
        $spans = $reader->extractTextWithPositions(0);

        $this->assertCount(2, $spans);
        // First text: x = 50 + 10 = 60
        $this->assertEqualsWithDelta(60.0, $spans[0]->x, 0.5);
        // Second text: CTM restored, x = 10
        $this->assertEqualsWithDelta(10.0, $spans[1]->x, 0.5);
    }

    // -----------------------------------------------------------------------
    // Form XObject text extraction
    // -----------------------------------------------------------------------

    public function testFormXObjectTextExtraction(): void
    {
        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $coreFont = new Type1Font(StandardFont::Helvetica);
        $font = $writer->addFont($coreFont);

        // Create a Form XObject with text
        $bbox = new PdfArray([
            new PdfNumber(0), new PdfNumber(0),
            new PdfNumber(200), new PdfNumber(50),
        ]);
        $xobjContent = "BT\n/{$font->getResourceName()} 10 Tf\n5 5 Td\n(XObject Text) Tj\nET";
        $formXObj = new FormXObject($bbox, $xobjContent);
        $formXObj->resources = new Resources();
        $formXObj->resources->addFont(
            $font->getResourceName(),
            new PdfReference($coreFont->objectNumber),
        );
        $writer->register($formXObj);

        $page->corePage()->resources->addXObject(
            'FX1',
            new PdfReference($formXObj->objectNumber),
        );
        $cs = $writer->addContentStream($page);
        $cs->doXObject('FX1');

        $bytes = $writer->generate();
        $reader = PdfReader::fromString($bytes);
        $spans = $reader->extractTextWithPositions(0);

        $this->assertNotEmpty($spans);
        $text = implode('', array_map(fn(TextSpan $s) => $s->text, $spans));
        $this->assertStringContainsString('XObject Text', $text);
    }

    // -----------------------------------------------------------------------
    // Edge cases and negative paths
    // -----------------------------------------------------------------------

    public function testEmptyPageReturnsNoSpans(): void
    {
        $writer = new PdfWriter(compressStreams: false);
        $writer->addPage(612, 792); // empty page — no content

        $bytes = $writer->generate();
        $reader = PdfReader::fromString($bytes);
        $spans = $reader->extractTextWithPositions(0);

        $this->assertSame([], $spans);
    }

    public function testGraphicsOnlyPageReturnsNoSpans(): void
    {
        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);

        $cs = $writer->addContentStream($page);
        // Only graphics, no text
        $cs->saveGraphicsState()
            ->setFillColorRGB(1, 0, 0)
            ->rectangle(50, 50, 100, 100)
            ->fill()
            ->restoreGraphicsState();

        $bytes = $writer->generate();
        $reader = PdfReader::fromString($bytes);
        $spans = $reader->extractTextWithPositions(0);

        $this->assertSame([], $spans);
    }

    public function testOutOfRangePageIndexThrows(): void
    {
        $writer = new PdfWriter(compressStreams: false);
        $writer->addPage(612, 792);

        $bytes = $writer->generate();
        $reader = PdfReader::fromString($bytes);

        $this->expectException(\OutOfRangeException::class);
        $reader->extractTextWithPositions(5);
    }

    public function testMultipleFontSizes(): void
    {
        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $font = $writer->addFont(new Type1Font(StandardFont::Helvetica));

        $cs = $writer->addContentStream($page);
        $cs->beginText()
            ->setFont($font->getResourceName(), 24)
            ->moveTextPosition(72, 720)
            ->showText('Big')
            ->endText();
        $cs->beginText()
            ->setFont($font->getResourceName(), 8)
            ->moveTextPosition(72, 680)
            ->showText('Small')
            ->endText();

        $bytes = $writer->generate();
        $reader = PdfReader::fromString($bytes);
        $spans = $reader->extractTextWithPositions(0);

        $this->assertCount(2, $spans);
        $this->assertEqualsWithDelta(24.0, $spans[0]->fontSize, 0.1);
        $this->assertEqualsWithDelta(8.0, $spans[1]->fontSize, 0.1);
        $this->assertGreaterThan($spans[1]->height, $spans[0]->height);
    }

    public function testDifferentFonts(): void
    {
        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $helv = $writer->addFont(new Type1Font(StandardFont::Helvetica));
        $times = $writer->addFont(new Type1Font(StandardFont::TimesRoman));

        $cs = $writer->addContentStream($page);
        $cs->beginText()
            ->setFont($helv->getResourceName(), 12)
            ->moveTextPosition(72, 720)
            ->showText('Helvetica text')
            ->endText();
        $cs->beginText()
            ->setFont($times->getResourceName(), 12)
            ->moveTextPosition(72, 700)
            ->showText('Times text')
            ->endText();

        $bytes = $writer->generate();
        $reader = PdfReader::fromString($bytes);
        $spans = $reader->extractTextWithPositions(0);

        $this->assertCount(2, $spans);
        $this->assertSame($helv->getResourceName(), $spans[0]->fontName);
        $this->assertSame($times->getResourceName(), $spans[1]->fontName);
        // Different fonts have different widths for same-length text
        $this->assertNotEquals($spans[0]->width, $spans[1]->width);
    }

    // -----------------------------------------------------------------------
    // Integration with sample PDFs
    // -----------------------------------------------------------------------

    public function testSimpleTextSamplePdf(): void
    {
        $path = $this->samplePath('simple_text.pdf');
        if (!file_exists($path)) {
            $this->markTestSkipped('simple_text.pdf not found');
        }

        $reader = PdfReader::fromFile($path);
        $spans = $reader->extractTextWithPositions(0);

        $this->assertNotEmpty($spans);
        // Every span should have valid positioning
        foreach ($spans as $span) {
            $this->assertInstanceOf(TextSpan::class, $span);
            $this->assertNotSame('', $span->text);
            $this->assertGreaterThan(0, $span->fontSize);
            $this->assertGreaterThanOrEqual(0, $span->width);
            $this->assertGreaterThan(0, $span->height);
        }
    }

    public function testEmbeddedFontSamplePdf(): void
    {
        $path = $this->samplePath('embedded_fonts.pdf');
        if (!file_exists($path)) {
            $this->markTestSkipped('embedded_fonts.pdf not found');
        }

        $reader = PdfReader::fromFile($path);
        $spans = $reader->extractTextWithPositions(0);

        $this->assertNotEmpty($spans);
        foreach ($spans as $span) {
            $this->assertInstanceOf(TextSpan::class, $span);
            $this->assertGreaterThanOrEqual(0, $span->width);
        }
    }

    public function testMultiPageComplexSamplePdf(): void
    {
        $path = $this->samplePath('multi_page_complex.pdf');
        if (!file_exists($path)) {
            $this->markTestSkipped('multi_page_complex.pdf not found');
        }

        $reader = PdfReader::fromFile($path);
        $allSpans = $reader->extractAllTextWithPositions();

        $this->assertNotEmpty($allSpans);
        $totalSpans = 0;
        foreach ($allSpans as $pageSpans) {
            $totalSpans += count($pageSpans);
        }
        $this->assertGreaterThan(0, $totalSpans);
    }

    // -----------------------------------------------------------------------
    // Text state operators (Tc, Tw, Tz)
    // -----------------------------------------------------------------------

    public function testCharSpacingAffectsWidth(): void
    {
        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $font = $writer->addFont(new Type1Font(StandardFont::Courier));

        $cs = $writer->addContentStream($page);
        // Normal spacing
        $cs->beginText()
            ->setFont($font->getResourceName(), 12)
            ->moveTextPosition(72, 720)
            ->showText('AAAA')
            ->endText();
        // Extra char spacing
        $cs->beginText()
            ->setFont($font->getResourceName(), 12)
            ->setCharSpacing(5)
            ->moveTextPosition(72, 700)
            ->showText('AAAA')
            ->endText();

        $bytes = $writer->generate();
        $reader = PdfReader::fromString($bytes);
        $spans = $reader->extractTextWithPositions(0);

        $this->assertCount(2, $spans);
        // With extra char spacing, the span should be wider
        $this->assertGreaterThan($spans[0]->width, $spans[1]->width);
    }

    public function testHorizontalScalingAffectsWidth(): void
    {
        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $font = $writer->addFont(new Type1Font(StandardFont::Courier));

        $cs = $writer->addContentStream($page);
        // Normal scaling (100%)
        $cs->beginText()
            ->setFont($font->getResourceName(), 12)
            ->setHorizontalScaling(100)
            ->moveTextPosition(72, 720)
            ->showText('Test')
            ->endText();
        // 200% horizontal scaling
        $cs->beginText()
            ->setFont($font->getResourceName(), 12)
            ->setHorizontalScaling(200)
            ->moveTextPosition(72, 700)
            ->showText('Test')
            ->endText();

        $bytes = $writer->generate();
        $reader = PdfReader::fromString($bytes);
        $spans = $reader->extractTextWithPositions(0);

        $this->assertCount(2, $spans);
        $ratio = $spans[1]->width / $spans[0]->width;
        $this->assertEqualsWithDelta(2.0, $ratio, 0.1);
    }

    // -----------------------------------------------------------------------
    // TD operator sets text leading
    // -----------------------------------------------------------------------

    public function testTDOperatorSetsLeading(): void
    {
        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $font = $writer->addFont(new Type1Font(StandardFont::Helvetica));

        $cs = $writer->addContentStream($page);
        $cs->beginText()
            ->setFont($font->getResourceName(), 12)
            ->moveTextPosition(72, 720)
            ->showText('Line 1')
            // TD sets leading to -ty, then moves
            ->moveTextPositionNewLine(0, -14)
            ->showText('Line 2')
            ->endText();

        $bytes = $writer->generate();
        $reader = PdfReader::fromString($bytes);
        $spans = $reader->extractTextWithPositions(0);

        $this->assertCount(2, $spans);
        $this->assertEqualsWithDelta(720.0, $spans[0]->y, 0.5);
        $this->assertEqualsWithDelta(706.0, $spans[1]->y, 0.5);
    }

    // -----------------------------------------------------------------------
    // Quote operators (' and ")
    // -----------------------------------------------------------------------

    public function testSingleQuoteOperator(): void
    {
        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $font = $writer->addFont(new Type1Font(StandardFont::Helvetica));

        // Use raw content stream to test ' operator
        $cs = $writer->addContentStream($page);
        $cs->beginText()
            ->setFont($font->getResourceName(), 12)
            ->setTextLeading(14)
            ->moveTextPosition(72, 720)
            ->showText('Line 1')
            ->moveToNextLineAndShowText('Line 2')
            ->endText();

        $bytes = $writer->generate();
        $reader = PdfReader::fromString($bytes);
        $spans = $reader->extractTextWithPositions(0);

        $this->assertCount(2, $spans);
        $this->assertSame('Line 1', $spans[0]->text);
        $this->assertSame('Line 2', $spans[1]->text);
        // Line 2 should be below line 1 (y decreased by leading)
        $this->assertLessThan($spans[0]->y, $spans[1]->y);
    }

    public function testDoubleQuoteOperator(): void
    {
        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $font = $writer->addFont(new Type1Font(StandardFont::Helvetica));

        $cs = $writer->addContentStream($page);
        $cs->beginText()
            ->setFont($font->getResourceName(), 12)
            ->setTextLeading(14)
            ->moveTextPosition(72, 720)
            ->showText('Line 1')
            ->setSpacingMoveAndShowText(0, 0, 'Line 2')
            ->endText();

        $bytes = $writer->generate();
        $reader = PdfReader::fromString($bytes);
        $spans = $reader->extractTextWithPositions(0);

        $this->assertCount(2, $spans);
        $this->assertSame('Line 1', $spans[0]->text);
        $this->assertSame('Line 2', $spans[1]->text);
    }

    // -----------------------------------------------------------------------
    // Span properties invariants
    // -----------------------------------------------------------------------

    public function testAllSpanPropertiesAreFinite(): void
    {
        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $font = $writer->addFont(new Type1Font(StandardFont::Helvetica));

        $cs = $writer->addContentStream($page);
        $cs->beginText()
            ->setFont($font->getResourceName(), 12)
            ->moveTextPosition(72, 720)
            ->showText('Finite check')
            ->endText();

        $bytes = $writer->generate();
        $reader = PdfReader::fromString($bytes);
        $spans = $reader->extractTextWithPositions(0);

        foreach ($spans as $span) {
            $this->assertTrue(is_finite($span->x), 'x should be finite');
            $this->assertTrue(is_finite($span->y), 'y should be finite');
            $this->assertTrue(is_finite($span->width), 'width should be finite');
            $this->assertTrue(is_finite($span->height), 'height should be finite');
            $this->assertTrue(is_finite($span->fontSize), 'fontSize should be finite');
        }
    }
}
