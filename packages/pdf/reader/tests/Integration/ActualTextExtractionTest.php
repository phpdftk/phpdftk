<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Reader\Tests\Integration;

use Phpdftk\Pdf\Core\Font\StandardFont;
use Phpdftk\Pdf\Core\Font\Type1Font;
use Phpdftk\Pdf\Reader\PdfReader;
use Phpdftk\Pdf\Writer\PdfWriter;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the /BDC + /ActualText + /EMC code path in PositionedTextExtractor,
 * which routes through extractActualText() and buildSpanForText().
 */
class ActualTextExtractionTest extends TestCase
{
    private function buildPdfWithActualText(string $propertyDict): string
    {
        $writer = new PdfWriter(compressStreams: false);
        $font = $writer->addFont(new Type1Font(StandardFont::Helvetica));
        $page = $writer->addPage(612, 792);
        $cs = $writer->addContentStream($page);

        // BT must come BEFORE the BDC because BT resets the marked-content
        // stack. Emit operators in the order: BT, font, position, BDC, Tj, EMC, ET.
        $cs->beginText()
            ->setFont($font->getResourceName(), 12)
            ->moveTextPosition(72, 720);
        $cs->raw('/Span ' . $propertyDict . ' BDC');
        $cs->raw('(raw glyphs) Tj');
        $cs->raw('EMC');
        $cs->endText();
        return $writer->generate();
    }

    public function testExtractActualTextOverridesRawGlyphs(): void
    {
        $reader = PdfReader::fromString($this->buildPdfWithActualText('<< /ActualText (correct text) >>'));
        $spans = $reader->extractTextWithPositions(0);

        $combined = '';
        foreach ($spans as $span) {
            $combined .= $span->text;
        }
        $this->assertStringContainsString('correct text', $combined);
    }

    public function testExtractActualTextWithHexEncodedString(): void
    {
        // /ActualText <68656c6c6f> = "hello"
        $reader = PdfReader::fromString($this->buildPdfWithActualText('<< /ActualText <68656c6c6f> >>'));
        $spans = $reader->extractTextWithPositions(0);
        $combined = '';
        foreach ($spans as $span) {
            $combined .= $span->text;
        }
        $this->assertStringContainsString('hello', $combined);
    }
}
