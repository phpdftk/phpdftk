<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Tests\Document;

use ApprLabs\Pdf\Core\Font\TrueTypeFont;
use ApprLabs\Pdf\Writer\PdfWriter;
use PHPUnit\Framework\TestCase;

class EmbeddedFontsTest extends TestCase
{
    private function findFont(): string
    {
        foreach ([
            '/System/Library/Fonts/Supplemental/Arial.ttf',
            '/System/Library/Fonts/Supplemental/Georgia.ttf',
            '/System/Library/Fonts/Supplemental/Verdana.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        ] as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }
        $this->markTestSkipped('No TTF font found on this system');
    }

    public function testGeneratesPdfWithEmbeddedFont(): void
    {
        $writer = new PdfWriter();
        $page = $writer->addPage(612, 792);
        $font = TrueTypeFont::fromFile($this->findFont());
        $name = $writer->addFont($font, $page);

        $cs = $writer->addContentStream($page);
        $cs->beginText()
           ->setFont($name, 12)
           ->moveTextPosition(72, 720)
           ->showText('Hello, embedded font!')
           ->endText();

        $outPath = __DIR__ . '/../../../../../docs/sample-pdfs/embedded_fonts.pdf';
        $writer->save($outPath);

        self::assertFileExists($outPath);
        self::assertStringStartsWith('%PDF', file_get_contents($outPath));
    }

    public function testEmbeddedFontHasFontDescriptor(): void
    {
        $writer = new PdfWriter();
        $page = $writer->addPage(612, 792);
        $font = TrueTypeFont::fromFile($this->findFont());
        $writer->addFont($font, $page);

        self::assertNotNull($font->fontDescriptor);
    }

    public function testEmbeddedFontHasToUnicode(): void
    {
        $writer = new PdfWriter();
        $page = $writer->addPage(612, 792);
        $font = TrueTypeFont::fromFile($this->findFont());
        $writer->addFont($font, $page);

        self::assertNotNull($font->toUnicode);
    }

    public function testEmbeddedFontWidthsArray(): void
    {
        $font = TrueTypeFont::fromFile($this->findFont());

        self::assertNotNull($font->widths);
        self::assertCount(224, $font->widths->items);
    }
}
