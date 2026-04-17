<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Reader\Tests\Integration;

use ApprLabs\Pdf\Core\Font\StandardFont;
use ApprLabs\Pdf\Core\Font\Type1Font;
use ApprLabs\Pdf\Reader\Parser\ContentStreamOp;
use ApprLabs\Pdf\Reader\Parser\ContentStreamParser;
use ApprLabs\Pdf\Reader\PdfReader;
use ApprLabs\Pdf\Writer\PdfWriter;
use PHPUnit\Framework\TestCase;

class TextExtractionTest extends TestCase
{
    private function samplePath(string $name): string
    {
        return __DIR__ . '/../../../../../docs/sample-pdfs/' . $name;
    }

    // -----------------------------------------------------------------------
    // ContentStreamParser unit tests
    // -----------------------------------------------------------------------

    public function testParseSimpleTextBlock(): void
    {
        $parser = new ContentStreamParser();
        $ops = $parser->parse("BT\n/F1 12 Tf\n72 720 Td\n(Hello World) Tj\nET");

        $this->assertCount(5, $ops);
        $this->assertSame('BT', $ops[0]->operator);
        $this->assertSame('Tf', $ops[1]->operator);
        $this->assertSame(['/F1', '12'], $ops[1]->operands);
        $this->assertSame('Td', $ops[2]->operator);
        $this->assertSame(['72', '720'], $ops[2]->operands);
        $this->assertSame('Tj', $ops[3]->operator);
        $this->assertSame(['(Hello World)'], $ops[3]->operands);
        $this->assertSame('ET', $ops[4]->operator);
    }

    public function testParseGraphicsOperators(): void
    {
        $parser = new ContentStreamParser();
        $ops = $parser->parse("q\n0.5 0.5 0.5 rg\n100 200 300 400 re\nf\nQ");

        $this->assertCount(5, $ops);
        $this->assertSame('q', $ops[0]->operator);
        $this->assertSame('rg', $ops[1]->operator);
        $this->assertSame('re', $ops[2]->operator);
        $this->assertSame('f', $ops[3]->operator);
        $this->assertSame('Q', $ops[4]->operator);
    }

    public function testParseTJArray(): void
    {
        $parser = new ContentStreamParser();
        $ops = $parser->parse("[(Hello) -80 (World)] TJ");

        $this->assertCount(1, $ops);
        $this->assertSame('TJ', $ops[0]->operator);
        $this->assertCount(1, $ops[0]->operands);
    }

    public function testParseHexString(): void
    {
        $parser = new ContentStreamParser();
        $ops = $parser->parse("<48656C6C6F> Tj");

        $this->assertCount(1, $ops);
        $this->assertSame('Tj', $ops[0]->operator);
        $this->assertSame('<48656C6C6F>', $ops[0]->operands[0]);
    }

    public function testParseComment(): void
    {
        $parser = new ContentStreamParser();
        $ops = $parser->parse("% This is a comment\nBT\nET");

        $this->assertCount(2, $ops);
        $this->assertSame('BT', $ops[0]->operator);
        $this->assertSame('ET', $ops[1]->operator);
    }

    public function testParseEmptyStream(): void
    {
        $parser = new ContentStreamParser();
        $ops = $parser->parse('');
        $this->assertCount(0, $ops);
    }

    // -----------------------------------------------------------------------
    // Text extraction integration tests
    // -----------------------------------------------------------------------

    public function testExtractSimpleText(): void
    {
        $reader = PdfReader::fromFile($this->samplePath('simple_text.pdf'));

        $text = $reader->extractText(0);
        $this->assertStringContainsString('Hello World', $text);
    }

    public function testExtractMultiplePages(): void
    {
        $reader = PdfReader::fromFile($this->samplePath('simple_text.pdf'));

        // Page 0
        $text0 = $reader->extractText(0);
        $this->assertStringContainsString('Hello World', $text0);

        // Page 1
        $text1 = $reader->extractText(1);
        $this->assertStringContainsString('Line 1', $text1);
        $this->assertStringContainsString('Line 2', $text1);

        // Page 2
        $text2 = $reader->extractText(2);
        $this->assertStringContainsString('Courier', $text2);
        $this->assertStringContainsString('monospaced', $text2);
    }

    public function testExtractMultiPageComplex(): void
    {
        $reader = PdfReader::fromFile($this->samplePath('multi_page_complex.pdf'));

        $text = $reader->extractText(0);
        $this->assertStringContainsString('Page 1 of 10', $text);
        $this->assertStringContainsString('quick brown fox', $text);
        $this->assertStringContainsString('Lorem ipsum', $text);
    }

    public function testExtractUnicodeEmDash(): void
    {
        $reader = PdfReader::fromFile($this->samplePath('multi_page_complex.pdf'));

        $text = $reader->extractText(0);
        // Em-dash (U+2014) should be preserved
        $this->assertStringContainsString("\u{2014}", $text);
    }

    public function testExtractEmbeddedFonts(): void
    {
        $reader = PdfReader::fromFile($this->samplePath('embedded_fonts.pdf'));

        $text = $reader->extractText(0);
        $this->assertStringContainsString('Hello', $text);
        $this->assertStringContainsString('embedded font', $text);
    }

    public function testExtractHighLevelPdf(): void
    {
        $reader = PdfReader::fromFile($this->samplePath('high_level_pdf.pdf'));

        $text = $reader->extractText(0);
        $this->assertStringContainsString('phpdftk', $text);
        $this->assertStringContainsString('High-Level Builder', $text);
    }

    public function testExtractAllText(): void
    {
        $reader = PdfReader::fromFile($this->samplePath('simple_text.pdf'));

        $all = $reader->extractAllText();
        // Should contain text from all 3 pages
        $this->assertStringContainsString('Hello World', $all);
        $this->assertStringContainsString('Line 1', $all);
        $this->assertStringContainsString('Courier', $all);
    }

    public function testExtractFpdfGeneratedPdf(): void
    {
        $reader = PdfReader::fromFile($this->samplePath('bench_10pages.pdf'));

        $text = $reader->extractText(0);
        $this->assertStringContainsString('Page 1 line 0', $text);
        $this->assertStringContainsString('quick brown fox', $text);
    }

    public function testExtractPageWithGraphicsOnly(): void
    {
        $reader = PdfReader::fromFile($this->samplePath('graphics.pdf'));

        // Graphics-only page should produce empty or minimal text
        $text = $reader->extractText(0);
        // Should not crash, may have empty text
        $this->assertIsString($text);
    }

    public function testExtractFromEmbeddedTrueTypeMulti(): void
    {
        $reader = PdfReader::fromFile($this->samplePath('embedded_truetype_multi.pdf'));

        $text = $reader->extractText(0);
        $this->assertStringContainsString('Embedded TrueType Font', $text);
    }

    public function testExtractTextOutOfRangeThrows(): void
    {
        $reader = PdfReader::fromFile($this->samplePath('simple_text.pdf'));
        $this->expectException(\OutOfRangeException::class);
        $reader->extractText(999);
    }

    // -----------------------------------------------------------------------
    // ActualText / Marked Content tests
    // -----------------------------------------------------------------------

    public function testActualTextOverridesRawGlyphs(): void
    {
        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $fontName = $writer->addFont(new Type1Font(StandardFont::Helvetica));
        $content = $writer->addContentStream($page);

        $content->beginText()
            ->setFont($fontName, 12)
            ->moveTextPosition(72, 720)
            ->beginMarkedContentWithProperties('Span', '<< /ActualText (Hello World) >>')
            ->showText('raw glyphs here')
            ->endMarkedContent()
            ->endText();

        $pdfBytes = $writer->toBytes();

        $reader = PdfReader::fromString($pdfBytes);
        $text = $reader->extractText(0);

        $this->assertStringContainsString('Hello World', $text);
        $this->assertStringNotContainsString('raw glyphs here', $text);
    }

    public function testNestedMarkedContentPreservesText(): void
    {
        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $fontName = $writer->addFont(new Type1Font(StandardFont::Helvetica));
        $content = $writer->addContentStream($page);

        $content->beginText()
            ->setFont($fontName, 12)
            ->moveTextPosition(72, 720)
            ->beginMarkedContent('P')
            ->showText('Normal text')
            ->endMarkedContent()
            ->endText();

        $pdfBytes = $writer->toBytes();

        $reader = PdfReader::fromString($pdfBytes);
        $text = $reader->extractText(0);

        $this->assertStringContainsString('Normal text', $text);
    }
}
