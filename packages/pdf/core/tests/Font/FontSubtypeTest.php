<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Tests\Font;

use Phpdftk\Pdf\Core\Font\CIDFontType0Font;
use Phpdftk\Pdf\Core\Font\CIDFontType2Font;
use Phpdftk\Pdf\Core\Font\CIDSystemInfo;
use Phpdftk\Pdf\Core\Font\MMType1Font;
use Phpdftk\Pdf\Core\Font\Type3Font;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfReference;
use PHPUnit\Framework\TestCase;

class FontSubtypeTest extends TestCase
{
    // -----------------------------------------------------------------------
    // Type3Font
    // -----------------------------------------------------------------------

    public function testType3FontSubtype(): void
    {
        $font = new Type3Font('MyType3');
        $font->objectNumber = 1;
        $pdf = $font->toPdf();
        self::assertStringContainsString('/Subtype /Type3', $pdf);
    }

    public function testType3FontTypeIsFont(): void
    {
        $font = new Type3Font();
        $font->objectNumber = 1;
        self::assertStringContainsString('/Type /Font', $font->toPdf());
    }

    public function testType3FontDefaultFontMatrix(): void
    {
        $font = new Type3Font();
        $font->objectNumber = 1;
        // Default [0.001 0 0 0.001 0 0]
        self::assertStringContainsString('/FontMatrix', $font->toPdf());
    }

    public function testType3FontRequiredFields(): void
    {
        $font = new Type3Font('MyType3');
        $font->objectNumber = 1;
        $font->fontBBox = new PdfArray([
            new PdfNumber(0), new PdfNumber(0),
            new PdfNumber(750), new PdfNumber(750),
        ]);
        $font->firstChar = 65;
        $font->lastChar = 65;
        $font->widths = new PdfArray([new PdfNumber(600)]);
        $font->encoding = new PdfReference(5);
        $font->addCharProc('A', new PdfReference(6));

        $pdf = $font->toPdf();
        self::assertStringContainsString('/FontBBox', $pdf);
        self::assertStringContainsString('/FirstChar 65', $pdf);
        self::assertStringContainsString('/LastChar 65', $pdf);
        self::assertStringContainsString('/Widths', $pdf);
        self::assertStringContainsString('/Encoding 5 0 R', $pdf);
        self::assertStringContainsString('/CharProcs', $pdf);
        self::assertStringContainsString('/A 6 0 R', $pdf);
    }

    public function testType3FontWithResources(): void
    {
        $font = new Type3Font();
        $font->objectNumber = 1;
        $font->resources = new \Phpdftk\Pdf\Core\PdfDictionary([
            'ProcSet' => new PdfArray([new PdfName('PDF')]),
        ]);
        self::assertStringContainsString('/Resources', $font->toPdf());
    }

    // -----------------------------------------------------------------------
    // MMType1Font
    // -----------------------------------------------------------------------

    public function testMMType1Subtype(): void
    {
        $font = new MMType1Font('MyriadMM');
        $font->objectNumber = 1;
        self::assertStringContainsString('/Subtype /MMType1', $font->toPdf());
    }

    public function testMMType1BaseFont(): void
    {
        $font = new MMType1Font('MyriadMM');
        $font->objectNumber = 1;
        self::assertStringContainsString('/BaseFont /MyriadMM', $font->toPdf());
    }

    public function testMMType1EncodesSpacesInInstanceName(): void
    {
        // "MyriadMM_215 WG_600 RG" → spaces become underscores
        $font = new MMType1Font('MyriadMM_215 WG_600 RG');
        $font->objectNumber = 1;
        $pdf = $font->toPdf();
        self::assertStringContainsString('/BaseFont /MyriadMM_215_WG_600_RG', $pdf);
    }

    // -----------------------------------------------------------------------
    // CIDFontType0Font
    // -----------------------------------------------------------------------

    public function testCIDFontType0Subtype(): void
    {
        $font = new CIDFontType0Font(
            'KozMin-Regular',
            new CIDSystemInfo('Adobe', 'Japan1', 6),
        );
        $font->objectNumber = 1;
        self::assertStringContainsString('/Subtype /CIDFontType0', $font->toPdf());
    }

    public function testCIDFontType0BaseFont(): void
    {
        $font = new CIDFontType0Font(
            'KozMin-Regular',
            new CIDSystemInfo('Adobe', 'Japan1', 6),
        );
        $font->objectNumber = 1;
        self::assertStringContainsString('/BaseFont /KozMin-Regular', $font->toPdf());
    }

    // -----------------------------------------------------------------------
    // CIDFontType2Font
    // -----------------------------------------------------------------------

    public function testCIDFontType2Subtype(): void
    {
        $font = new CIDFontType2Font(
            'Arial',
            new CIDSystemInfo('Adobe', 'Identity', 0),
        );
        $font->objectNumber = 1;
        self::assertStringContainsString('/Subtype /CIDFontType2', $font->toPdf());
    }

    public function testCIDFontType2DefaultCIDToGIDMap(): void
    {
        $font = new CIDFontType2Font(
            'Arial',
            new CIDSystemInfo('Adobe', 'Identity', 0),
        );
        $font->objectNumber = 1;
        self::assertStringContainsString('/CIDToGIDMap /Identity', $font->toPdf());
    }

    public function testCIDFontType2ExplicitCIDToGIDMapStream(): void
    {
        $font = new CIDFontType2Font(
            'Arial',
            new CIDSystemInfo('Adobe', 'Identity', 0),
        );
        $font->objectNumber = 1;
        $font->cidToGidMap = new PdfReference(42);
        self::assertStringContainsString('/CIDToGIDMap 42 0 R', $font->toPdf());
    }

    public function testCIDFontType2WithWidths(): void
    {
        $font = new CIDFontType2Font(
            'Arial',
            new CIDSystemInfo('Adobe', 'Identity', 0),
        );
        $font->objectNumber = 1;
        $font->dw = 1000;
        $font->w = new PdfArray([new PdfNumber(32), new PdfArray([new PdfNumber(500)])]);
        $pdf = $font->toPdf();
        self::assertStringContainsString('/DW 1000', $pdf);
        self::assertStringContainsString('/W', $pdf);
    }
}
