<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Tests\Document;

use ApprLabs\FontParser\Type1Parser;
use ApprLabs\Pdf\Core\Font\Type1Font;
use ApprLabs\Pdf\Writer\PdfWriter;
use ApprLabs\Tests\Support\QpdfValidationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group("qpdf")]
class EmbeddedType1FontTest extends TestCase
{
    use QpdfValidationTrait;

    /**
     * Build a minimal synthetic PFB font for testing.
     */
    private function buildSyntheticPfb(): string
    {
        $ascii = "%!PS-AdobeFont-1.0: SyntheticFont 001.000\n"
            . "/FontName /SyntheticFont def\n"
            . "/FullName (Synthetic Test Font) def\n"
            . "/FamilyName (Synthetic) def\n"
            . "/FontBBox {-100 -200 800 700} readonly def\n"
            . "/isFixedPitch false def\n"
            . "/ItalicAngle 0 def\n"
            . "/Encoding 256 array\n"
            . "0 1 255 { 1 index exch /.notdef put } for\n"
            . "dup 32 /space put\n"
            . "dup 65 /A put\n"
            . "dup 66 /B put\n"
            . "dup 67 /C put\n"
            . "dup 72 /H put\n"
            . "dup 101 /e put\n"
            . "dup 108 /l put\n"
            . "dup 111 /o put\n"
            . "readonly def\n"
            . "currentfile eexec\n";

        $binary = str_repeat("\x00", 64);
        $trailer = str_repeat("0", 512) . "\ncleartomark\n";

        $pfb = '';
        $pfb .= "\x80\x01" . pack('V', strlen($ascii)) . $ascii;
        $pfb .= "\x80\x02" . pack('V', strlen($binary)) . $binary;
        $pfb .= "\x80\x01" . pack('V', strlen($trailer)) . $trailer;
        $pfb .= "\x80\x03";

        return $pfb;
    }

    private function createType1FromSynthetic(): Type1Font
    {
        $pfb = $this->buildSyntheticPfb();
        $tmp = tempnam(sys_get_temp_dir(), 'phpdftk_t1_test_');
        file_put_contents($tmp, $pfb);
        return Type1Font::fromFile($tmp);
    }

    public function testFromFileReturnsType1Font(): void
    {
        $font = $this->createType1FromSynthetic();
        self::assertInstanceOf(Type1Font::class, $font);
        self::assertNotNull($font->parsedFontData);
    }

    public function testFromFileSetsBaseFont(): void
    {
        $font = $this->createType1FromSynthetic();
        self::assertSame('SyntheticFont', $font->baseFont->value);
    }

    public function testFromFileSetsWidths(): void
    {
        $font = $this->createType1FromSynthetic();
        self::assertSame(32, $font->firstChar);
        self::assertSame(255, $font->lastChar);
        self::assertNotNull($font->widths);
    }

    public function testEmbeddingCreatesDescriptor(): void
    {
        $writer = new PdfWriter();
        $page = $writer->addPage(612, 792);
        $font = $this->createType1FromSynthetic();
        $writer->addFont($font, $page);

        self::assertNotNull($font->fontDescriptor, 'FontDescriptor should be set after embedding');
    }

    public function testEmbeddingCreatesToUnicode(): void
    {
        $writer = new PdfWriter();
        $page = $writer->addPage(612, 792);
        $font = $this->createType1FromSynthetic();
        $writer->addFont($font, $page);

        self::assertNotNull($font->toUnicode, 'ToUnicode should be set after embedding');
    }

    public function testGeneratesPdfWithEmbeddedType1(): void
    {
        $writer = new PdfWriter();
        $page = $writer->addPage(612, 792);
        $font = $this->createType1FromSynthetic();
        $name = $writer->addFont($font, $page)->getResourceName();

        $cs = $writer->addContentStream($page);
        $cs->beginText()
           ->setFont($name, 24)
           ->moveTextPosition(72, 700)
           ->showText('Hello ABC')
           ->endText();

        $outPath = __DIR__ . '/../output/embedded_type1_font.pdf';
        $writer->save($outPath);

        self::assertFileExists($outPath);
        $this->assertQpdfValid($outPath);
        $content = file_get_contents($outPath);
        self::assertStringStartsWith('%PDF', $content);

        // Verify Type1-specific objects are in the output
        self::assertStringContainsString('/Type /FontDescriptor', $content);
        self::assertStringContainsString('/FontFile', $content);
        self::assertStringContainsString('/Length1', $content);
        self::assertStringContainsString('/Length2', $content);
        self::assertStringContainsString('/Length3', $content);
        self::assertStringContainsString('/Subtype /Type1', $content);
    }

    public function testPdfOutputContainsFontData(): void
    {
        $writer = new PdfWriter();
        $page = $writer->addPage(612, 792);
        $font = $this->createType1FromSynthetic();
        $writer->addFont($font, $page);

        $output = $writer->toBytes();

        // The output should contain the font name
        self::assertStringContainsString('/SyntheticFont', $output);
        // The output should have a ToUnicode reference wired to the font
        self::assertNotNull($font->toUnicode, 'ToUnicode reference should be set');
    }
}
