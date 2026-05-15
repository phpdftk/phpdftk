<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Reader\Tests\Integration;

use Phpdftk\Pdf\Core\Font\StandardFont;
use Phpdftk\Pdf\Core\Font\Type1Font;
use Phpdftk\Pdf\Reader\PdfReader;
use Phpdftk\Pdf\Writer\PdfWriter;
use PHPUnit\Framework\TestCase;

/**
 * Exercises TextExtractor's TJ array decoder via end-to-end extraction.
 *
 * The TJ operator (showTextArray) is what triggers `decodeTJArray()` —
 * a high-complexity method that parses array operands like
 * `[(Hello) -80 (World)]` and treats large negative numbers as spaces.
 */
class TextExtractorTJArrayTest extends TestCase
{
    private function buildPdfWithTJ(): string
    {
        $writer = new PdfWriter(compressStreams: false);
        $font = $writer->addFont(new Type1Font(StandardFont::Helvetica));
        $page = $writer->addPage(612, 792);
        $cs = $writer->addContentStream($page);

        // Use showTextArray to emit a TJ operator with mixed strings + spacing.
        // Large negative number (-200) should be interpreted as a word break (space).
        $cs->beginText()
            ->setFont($font->getResourceName(), 12)
            ->moveTextPosition(72, 720)
            ->showTextArray(['Hello', -200, 'World', 50, 'Mixed'])
            ->endText();

        return $writer->generate();
    }

    public function testExtractTextDecodesTJArrayLiteralStrings(): void
    {
        $reader = PdfReader::fromString($this->buildPdfWithTJ());
        $text = $reader->extractText(0);
        $this->assertStringContainsString('Hello', $text);
        $this->assertStringContainsString('World', $text);
        $this->assertStringContainsString('Mixed', $text);
    }

    public function testExtractTextTreatsLargeNegativeAsSpace(): void
    {
        $reader = PdfReader::fromString($this->buildPdfWithTJ());
        $text = $reader->extractText(0);
        // -200 < -100 so decodeTJArray injects a space between Hello and World.
        $this->assertStringContainsString('Hello World', $text);
    }

    public function testExtractTextNoSpaceInsertedForSmallNegativeOffsets(): void
    {
        $writer = new PdfWriter(compressStreams: false);
        $font = $writer->addFont(new Type1Font(StandardFont::Helvetica));
        $page = $writer->addPage(612, 792);
        $cs = $writer->addContentStream($page);
        $cs->beginText()
            ->setFont($font->getResourceName(), 12)
            ->moveTextPosition(72, 720)
            // -50 is small enough to be kerning, not a space
            ->showTextArray(['Ab', -50, 'cd'])
            ->endText();
        $reader = PdfReader::fromString($writer->generate());
        $text = $reader->extractText(0);
        // No space inserted — they should be glued together
        $this->assertStringContainsString('Abcd', $text);
    }

    public function testExtractAllTextWalksMultiplePages(): void
    {
        $writer = new PdfWriter(compressStreams: false);
        $font = $writer->addFont(new Type1Font(StandardFont::Helvetica));
        foreach (['Page1', 'Page2', 'Page3'] as $label) {
            $page = $writer->addPage(612, 792);
            $cs = $writer->addContentStream($page);
            $cs->beginText()
                ->setFont($font->getResourceName(), 12)
                ->moveTextPosition(72, 720)
                ->showTextArray([$label, -150, 'TJ'])
                ->endText();
        }
        $reader = PdfReader::fromString($writer->generate());
        $all = $reader->extractAllText();
        $this->assertStringContainsString('Page1', $all);
        $this->assertStringContainsString('Page2', $all);
        $this->assertStringContainsString('Page3', $all);
    }

    public function testExtractTextDecodesTJArrayHexStrings(): void
    {
        // Build content stream with a TJ array containing hex strings.
        // <48656c6c6f> = "Hello", <576f726c64> = "World"
        $writer = new PdfWriter(compressStreams: false);
        $font = $writer->addFont(new Type1Font(StandardFont::Helvetica));
        $page = $writer->addPage(612, 792);
        $cs = $writer->addContentStream($page);
        $cs->beginText()
            ->setFont($font->getResourceName(), 12)
            ->moveTextPosition(72, 720);
        $cs->raw('[<48656c6c6f> -200 <576f726c64>] TJ');
        $cs->endText();
        $reader = PdfReader::fromString($writer->generate());
        $text = $reader->extractText(0);
        $this->assertStringContainsString('Hello', $text);
        $this->assertStringContainsString('World', $text);
    }

    public function testExtractTextHandlesOctalEscapesInLiteralStrings(): void
    {
        // Octal escapes: \101 = 'A', \102 = 'B', \040 = ' '
        $writer = new PdfWriter(compressStreams: false);
        $font = $writer->addFont(new Type1Font(StandardFont::Helvetica));
        $page = $writer->addPage(612, 792);
        $cs = $writer->addContentStream($page);
        $cs->beginText()
            ->setFont($font->getResourceName(), 12)
            ->moveTextPosition(72, 720);
        $cs->raw('(\101\102\040\103) Tj');
        $cs->endText();
        $reader = PdfReader::fromString($writer->generate());
        $text = $reader->extractText(0);
        $this->assertStringContainsString('AB C', $text);
    }

    public function testExtractTextHandlesEscapedLiteralCharacters(): void
    {
        $writer = new PdfWriter(compressStreams: false);
        $font = $writer->addFont(new Type1Font(StandardFont::Helvetica));
        $page = $writer->addPage(612, 792);
        $cs = $writer->addContentStream($page);
        $cs->beginText()
            ->setFont($font->getResourceName(), 12)
            ->moveTextPosition(72, 720);
        // Backslash escapes for n, r, t, b, f, (, ), \
        $cs->raw('(parens \(in\) \\\\here) Tj');
        $cs->endText();
        $reader = PdfReader::fromString($writer->generate());
        $text = $reader->extractText(0);
        $this->assertStringContainsString('parens', $text);
        $this->assertStringContainsString('here', $text);
    }

    public function testPositionedExtractionHandlesOctalAndHexStringsInTJ(): void
    {
        // PositionedTextExtractor has its own readOctalOrLiteral and
        // extractHexString. Trigger both via a TJ array containing each.
        $writer = new PdfWriter(compressStreams: false);
        $font = $writer->addFont(new Type1Font(StandardFont::Helvetica));
        $page = $writer->addPage(612, 792);
        $cs = $writer->addContentStream($page);
        $cs->beginText()
            ->setFont($font->getResourceName(), 12)
            ->moveTextPosition(72, 720);
        // <48656c6c6f> = "Hello", (\101\102) = "AB"
        $cs->raw('[<48656c6c6f> -200 (\101\102)] TJ');
        $cs->endText();
        $reader = PdfReader::fromString($writer->generate());
        $spans = $reader->extractTextWithPositions(0);
        $all = '';
        foreach ($spans as $span) {
            $all .= $span->text;
        }
        $this->assertStringContainsString('Hello', $all);
        $this->assertStringContainsString('AB', $all);
    }
}
