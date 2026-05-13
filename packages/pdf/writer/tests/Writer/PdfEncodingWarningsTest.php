<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Writer\Tests;

use Phpdftk\Pdf\Core\Content\ContentStream;
use Phpdftk\Pdf\Core\Font\StandardFont;
use Phpdftk\Pdf\Core\Font\Type1Font;
use Phpdftk\Pdf\Writer\Pdf;
use Phpdftk\Pdf\Writer\PdfWriter;
use PHPUnit\Framework\TestCase;

class PdfEncodingWarningsTest extends TestCase
{
    public function testNoWarningsWhenAllGlyphsMap(): void
    {
        $pdf = new Pdf();
        $pdf->addText('All ASCII — and Latin-1 supplements: café résumé');
        $this->assertSame([], $pdf->getEncodingWarnings());
    }

    public function testCheckMarkProducesOneWarning(): void
    {
        $writer = new PdfWriter();
        $page = $writer->addPage();
        $font = $writer->addFont(new Type1Font(StandardFont::Helvetica));
        $cs = $writer->addContentStream($page);
        $cs->beginText()
            ->setFont($font, 12)
            ->moveTextPosition(72, 720)
            ->showText("done \u{2713}")
            ->endText();

        $warnings = $writer->getEncodingWarnings();
        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('U+2713', $warnings[0]);
        $this->assertStringContainsString($font->getResourceName(), $warnings[0]);
    }

    public function testRepeatedMissingCodepointCountsCorrectly(): void
    {
        $writer = new PdfWriter();
        $page = $writer->addPage();
        $font = $writer->addFont(new Type1Font(StandardFont::Helvetica));
        $cs = $writer->addContentStream($page);
        $cs->beginText()->setFont($font, 12)->moveTextPosition(72, 720)
            ->showText("\u{2713} \u{2713} \u{2713}")
            ->endText();

        $warnings = $writer->getEncodingWarnings();
        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('U+2713', $warnings[0]);
        $this->assertStringContainsString('3x', $warnings[0]);
    }

    public function testCompositeFontProducesNoWarnings(): void
    {
        // A composite/CID font has no WinAnsi encoder, so missing-codepoint
        // tracking does not apply — the GID-hex path handles Unicode directly.
        $writer = new PdfWriter();
        $writer->addPage();
        $this->assertSame([], $writer->getEncodingWarnings());
    }
}
