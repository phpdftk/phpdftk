<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Tests\Font;

use PHPUnit\Framework\TestCase;
use Phpdftk\Pdf\Core\Font\CIDFont;
use Phpdftk\Pdf\Core\Font\CIDSystemInfo;
use Phpdftk\Pdf\Core\Font\Encoding;
use Phpdftk\Pdf\Core\Font\Font;
use Phpdftk\Pdf\Core\Font\FontDescriptor;
use Phpdftk\Pdf\Core\Font\StandardFont;
use Phpdftk\Pdf\Core\Font\TrueTypeFont;
use Phpdftk\Pdf\Core\Font\Type0Font;
use Phpdftk\Pdf\Core\Font\Type1Font;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfString;

class FontTest extends TestCase
{
    // -----------------------------------------------------------------------
    // StandardFont enum
    // -----------------------------------------------------------------------

    public function testStandardFontHas14Cases(): void
    {
        $cases = StandardFont::cases();
        self::assertCount(14, $cases);
    }

    public function testStandardFontValues(): void
    {
        self::assertSame('Helvetica', StandardFont::Helvetica->value);
        self::assertSame('Helvetica-Bold', StandardFont::HelveticaBold->value);
        self::assertSame('Courier', StandardFont::Courier->value);
        self::assertSame('Symbol', StandardFont::Symbol->value);
        self::assertSame('ZapfDingbats', StandardFont::ZapfDingbats->value);
        self::assertSame('Times-Roman', StandardFont::TimesRoman->value);
    }

    public function testStandardFontAllCasesHaveValues(): void
    {
        foreach (StandardFont::cases() as $case) {
            self::assertNotEmpty($case->value);
        }
    }

    // -----------------------------------------------------------------------
    // Type1Font
    // -----------------------------------------------------------------------

    public function testType1FontSubtype(): void
    {
        $font = new Type1Font(StandardFont::Helvetica);
        $font->objectNumber = 1;
        $pdf = $font->toPdf();
        self::assertStringContainsString('/Subtype /Type1', $pdf);
    }

    public function testType1FontBaseFont(): void
    {
        $font = new Type1Font(StandardFont::Helvetica);
        $font->objectNumber = 1;
        $pdf = $font->toPdf();
        self::assertStringContainsString('/BaseFont /Helvetica', $pdf);
    }

    public function testType1FontWidthsPopulated(): void
    {
        $font = new Type1Font(StandardFont::Helvetica, embedWidths: true);
        $font->objectNumber = 1;
        $pdf = $font->toPdf();
        self::assertStringContainsString('/Widths', $pdf);
        self::assertStringContainsString('/FirstChar', $pdf);
        self::assertStringContainsString('/LastChar', $pdf);
    }

    public function testType1FontWithoutWidths(): void
    {
        $font = new Type1Font(StandardFont::Helvetica, embedWidths: false);
        $font->objectNumber = 1;
        $pdf = $font->toPdf();
        self::assertStringNotContainsString('/Widths', $pdf);
    }

    public function testType1FontWithCourierBold(): void
    {
        $font = new Type1Font(StandardFont::CourierBold);
        $font->objectNumber = 1;
        $pdf = $font->toPdf();
        self::assertStringContainsString('/BaseFont /Courier-Bold', $pdf);
    }

    public function testType1FontWithCustomName(): void
    {
        $font = new Type1Font('MyCustomFont');
        $font->objectNumber = 1;
        $pdf = $font->toPdf();
        self::assertStringContainsString('/BaseFont /MyCustomFont', $pdf);
        // Custom fonts don't have widths embedded
        self::assertStringNotContainsString('/Widths', $pdf);
    }

    public function testType1FontTypeIsFont(): void
    {
        $font = new Type1Font(StandardFont::Helvetica);
        $font->objectNumber = 1;
        $pdf = $font->toPdf();
        self::assertStringContainsString('/Type /Font', $pdf);
    }

    public function testType1FontTimesRoman(): void
    {
        $font = new Type1Font(StandardFont::TimesRoman);
        $font->objectNumber = 1;
        $pdf = $font->toPdf();
        self::assertStringContainsString('/BaseFont /Times-Roman', $pdf);
    }

    public function testType1FontSymbol(): void
    {
        $font = new Type1Font(StandardFont::Symbol, embedWidths: false);
        $font->objectNumber = 1;
        $pdf = $font->toPdf();
        self::assertStringContainsString('/BaseFont /Symbol', $pdf);
    }

    // -----------------------------------------------------------------------
    // TrueTypeFont
    // -----------------------------------------------------------------------

    public function testTrueTypeFontSubtype(): void
    {
        $font = new TrueTypeFont('Arial');
        $font->objectNumber = 1;
        $pdf = $font->toPdf();
        self::assertStringContainsString('/Subtype /TrueType', $pdf);
    }

    public function testTrueTypeFontBaseFont(): void
    {
        $font = new TrueTypeFont('Arial');
        $font->objectNumber = 1;
        $pdf = $font->toPdf();
        self::assertStringContainsString('/BaseFont /Arial', $pdf);
    }

    public function testTrueTypeFontTypeIsFont(): void
    {
        $font = new TrueTypeFont('Verdana');
        $font->objectNumber = 1;
        $pdf = $font->toPdf();
        self::assertStringContainsString('/Type /Font', $pdf);
    }

    public function testTrueTypeFontWithDescriptor(): void
    {
        $font = new TrueTypeFont('Arial');
        $font->objectNumber = 1;
        $font->fontDescriptor = new PdfReference(5);
        $pdf = $font->toPdf();
        self::assertStringContainsString('/FontDescriptor 5 0 R', $pdf);
    }

    public function testTrueTypeFontWithEncoding(): void
    {
        $font = new TrueTypeFont('Arial');
        $font->objectNumber = 1;
        $font->encoding = new PdfReference(6);
        $pdf = $font->toPdf();
        self::assertStringContainsString('/Encoding 6 0 R', $pdf);
    }

    // -----------------------------------------------------------------------
    // Type0Font
    // -----------------------------------------------------------------------

    public function testType0FontSubtype(): void
    {
        $descendant = new PdfArray([]);
        $font = new Type0Font('Arial-UnicodeMSP', $descendant);
        $font->objectNumber = 1;
        $pdf = $font->toPdf();
        self::assertStringContainsString('/Subtype /Type0', $pdf);
    }

    public function testType0FontBaseFont(): void
    {
        $descendant = new PdfArray([]);
        $font = new Type0Font('KozMin-Regular', $descendant);
        $font->objectNumber = 1;
        $pdf = $font->toPdf();
        self::assertStringContainsString('/BaseFont /KozMin-Regular', $pdf);
    }

    public function testType0FontTypeIsFont(): void
    {
        $descendant = new PdfArray([]);
        $font = new Type0Font('TestFont', $descendant);
        $font->objectNumber = 1;
        $pdf = $font->toPdf();
        self::assertStringContainsString('/Type /Font', $pdf);
    }

    public function testType0FontDescendantFonts(): void
    {
        $cidFont = new CIDFont(
            'CIDFontType2',
            'Arial',
            new CIDSystemInfo('Adobe', 'Identity', 0),
        );
        $cidFont->objectNumber = 5;
        $descendant = new PdfArray([new PdfReference($cidFont->objectNumber)]);
        $font = new Type0Font('Arial-Identity-H', $descendant);
        $font->objectNumber = 1;
        $pdf = $font->toPdf();
        self::assertStringContainsString('/DescendantFonts', $pdf);
    }

    public function testType0FontWithEncoding(): void
    {
        $descendant = new PdfArray([]);
        $encRef = new PdfReference(7);
        $font = new Type0Font('TestFont', $descendant, $encRef);
        $font->objectNumber = 1;
        $pdf = $font->toPdf();
        self::assertStringContainsString('/Encoding 7 0 R', $pdf);
    }

    // -----------------------------------------------------------------------
    // CIDFont
    // -----------------------------------------------------------------------

    public function testCIDFontSubtype(): void
    {
        $cidInfo = new CIDSystemInfo('Adobe', 'Identity', 0);
        $font = new CIDFont('CIDFontType2', 'Arial', $cidInfo);
        $font->objectNumber = 1;
        $pdf = $font->toPdf();
        self::assertStringContainsString('/Subtype /CIDFontType2', $pdf);
    }

    public function testCIDFontType(): void
    {
        $cidInfo = new CIDSystemInfo('Adobe', 'Identity', 0);
        $font = new CIDFont('CIDFontType0', 'Kozuka', $cidInfo);
        $font->objectNumber = 1;
        $pdf = $font->toPdf();
        self::assertStringContainsString('/Type /Font', $pdf);
    }

    public function testCIDFontBaseFont(): void
    {
        $cidInfo = new CIDSystemInfo('Adobe', 'Identity', 0);
        $font = new CIDFont('CIDFontType2', 'MyriadPro', $cidInfo);
        $font->objectNumber = 1;
        $pdf = $font->toPdf();
        self::assertStringContainsString('/BaseFont /MyriadPro', $pdf);
    }

    public function testCIDFontWithDefaultWidth(): void
    {
        $cidInfo = new CIDSystemInfo('Adobe', 'Identity', 0);
        $font = new CIDFont('CIDFontType2', 'TestFont', $cidInfo);
        $font->objectNumber = 1;
        $font->dw = 1000;
        $pdf = $font->toPdf();
        self::assertStringContainsString('/DW 1000', $pdf);
    }

    public function testCIDFontWithFontDescriptor(): void
    {
        $cidInfo = new CIDSystemInfo('Adobe', 'Identity', 0);
        $font = new CIDFont('CIDFontType2', 'TestFont', $cidInfo);
        $font->objectNumber = 1;
        $font->fontDescriptor = new PdfReference(5);
        $pdf = $font->toPdf();
        self::assertStringContainsString('/FontDescriptor 5 0 R', $pdf);
    }

    // -----------------------------------------------------------------------
    // FontDescriptor
    // -----------------------------------------------------------------------

    public function testFontDescriptorType(): void
    {
        $fd = new FontDescriptor(new PdfName('Helvetica'));
        $fd->objectNumber = 1;
        $pdf = $fd->toPdf();
        self::assertStringContainsString('/Type /FontDescriptor', $pdf);
    }

    public function testFontDescriptorFontName(): void
    {
        $fd = new FontDescriptor(new PdfName('Helvetica'));
        $fd->objectNumber = 1;
        $pdf = $fd->toPdf();
        self::assertStringContainsString('/FontName /Helvetica', $pdf);
    }

    public function testFontDescriptorFlags(): void
    {
        $fd = new FontDescriptor(new PdfName('Helvetica'));
        $fd->objectNumber = 1;
        $fd->flags = 32;
        $pdf = $fd->toPdf();
        self::assertStringContainsString('/Flags 32', $pdf);
    }

    public function testFontDescriptorItalicAngle(): void
    {
        $fd = new FontDescriptor(new PdfName('Helvetica-Oblique'));
        $fd->objectNumber = 1;
        $fd->italicAngle = -12.0;
        $pdf = $fd->toPdf();
        self::assertStringContainsString('/ItalicAngle', $pdf);
    }

    public function testFontDescriptorAscent(): void
    {
        $fd = new FontDescriptor(new PdfName('Helvetica'));
        $fd->objectNumber = 1;
        $fd->ascent = 718;
        $pdf = $fd->toPdf();
        self::assertStringContainsString('/Ascent 718', $pdf);
    }

    public function testFontDescriptorDescent(): void
    {
        $fd = new FontDescriptor(new PdfName('Helvetica'));
        $fd->objectNumber = 1;
        $fd->descent = -207;
        $pdf = $fd->toPdf();
        self::assertStringContainsString('/Descent', $pdf);
    }

    public function testFontDescriptorCapHeight(): void
    {
        $fd = new FontDescriptor(new PdfName('Helvetica'));
        $fd->objectNumber = 1;
        $fd->capHeight = 718;
        $pdf = $fd->toPdf();
        self::assertStringContainsString('/CapHeight 718', $pdf);
    }

    public function testFontDescriptorStemV(): void
    {
        $fd = new FontDescriptor(new PdfName('Helvetica'));
        $fd->objectNumber = 1;
        $fd->stemV = 88;
        $pdf = $fd->toPdf();
        self::assertStringContainsString('/StemV 88', $pdf);
    }

    public function testFontDescriptorFontBBox(): void
    {
        $fd = new FontDescriptor(new PdfName('Helvetica'));
        $fd->objectNumber = 1;
        $fd->fontBBox = new PdfArray([
            new PdfNumber(-166), new PdfNumber(-225),
            new PdfNumber(1000), new PdfNumber(931),
        ]);
        $pdf = $fd->toPdf();
        self::assertStringContainsString('/FontBBox', $pdf);
    }

    public function testFontDescriptorFontFile2(): void
    {
        $fd = new FontDescriptor(new PdfName('Arial'));
        $fd->objectNumber = 1;
        $fd->fontFile2 = new PdfReference(8);
        $pdf = $fd->toPdf();
        self::assertStringContainsString('/FontFile2 8 0 R', $pdf);
    }

    public function testFontDescriptorMissingWidth(): void
    {
        $fd = new FontDescriptor(new PdfName('MyFont'));
        $fd->objectNumber = 1;
        $fd->missingWidth = 500;
        $pdf = $fd->toPdf();
        self::assertStringContainsString('/MissingWidth 500', $pdf);
    }

    // -----------------------------------------------------------------------
    // Encoding
    // -----------------------------------------------------------------------

    public function testEncodingType(): void
    {
        $enc = new Encoding();
        $enc->objectNumber = 1;
        $pdf = $enc->toPdf();
        self::assertStringContainsString('/Type /Encoding', $pdf);
    }

    public function testEncodingWithBaseEncoding(): void
    {
        $enc = new Encoding();
        $enc->objectNumber = 1;
        $enc->baseEncoding = new PdfName('WinAnsiEncoding');
        $pdf = $enc->toPdf();
        self::assertStringContainsString('/BaseEncoding /WinAnsiEncoding', $pdf);
    }

    public function testEncodingWithDifferences(): void
    {
        $enc = new Encoding();
        $enc->objectNumber = 1;
        $enc->differences = new PdfArray([
            new PdfNumber(128),
            new PdfName('euro'),
        ]);
        $pdf = $enc->toPdf();
        self::assertStringContainsString('/Differences', $pdf);
    }

    public function testEncodingEmpty(): void
    {
        $enc = new Encoding();
        $enc->objectNumber = 1;
        $pdf = $enc->toPdf();
        // Should contain type but no optional fields
        self::assertStringContainsString('/Type /Encoding', $pdf);
        self::assertStringNotContainsString('/BaseEncoding', $pdf);
    }

    // -----------------------------------------------------------------------
    // Font base class with optional fields
    // -----------------------------------------------------------------------

    public function testFontWithToUnicode(): void
    {
        $font = new TrueTypeFont('Arial');
        $font->objectNumber = 1;
        $font->toUnicode = new PdfReference(9);
        $pdf = $font->toPdf();
        self::assertStringContainsString('/ToUnicode 9 0 R', $pdf);
    }

    public function testFontWithFirstAndLastChar(): void
    {
        $font = new TrueTypeFont('Arial');
        $font->objectNumber = 1;
        $font->firstChar = 32;
        $font->lastChar = 255;
        $pdf = $font->toPdf();
        self::assertStringContainsString('/FirstChar 32', $pdf);
        self::assertStringContainsString('/LastChar 255', $pdf);
    }

    // -----------------------------------------------------------------------
    // TrueTypeFont::fromFile
    // -----------------------------------------------------------------------

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

    public function testTrueTypeFontFromFile(): void
    {
        $font = TrueTypeFont::fromFile($this->findFont());
        self::assertInstanceOf(TrueTypeFont::class, $font);
    }

    public function testTrueTypeFontFromFileHasParsedData(): void
    {
        $font = TrueTypeFont::fromFile($this->findFont());
        self::assertNotNull($font->parsedFontData);
    }

    public function testTrueTypeFontFromFileHasWidths(): void
    {
        $font = TrueTypeFont::fromFile($this->findFont());
        self::assertNotNull($font->widths);
    }
}
