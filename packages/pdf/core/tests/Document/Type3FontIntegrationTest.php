<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Tests\Document;

use ApprLabs\Pdf\Core\Content\ContentStream;
use ApprLabs\Pdf\Core\Font\Encoding;
use ApprLabs\Pdf\Core\Font\Type3Font;
use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\PdfReference;
use ApprLabs\Pdf\Writer\PdfWriter;
use ApprLabs\Tests\Support\QpdfValidationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Generates a real PDF that paints glyphs from a custom Type 3 font whose
 * glyph procedures are inline content streams.
 */
#[Group("qpdf")]
class Type3FontIntegrationTest extends TestCase
{
    use QpdfValidationTrait;

    private const OUTPUT_FILE = __DIR__ . '/../../../../../docs/sample-pdfs/type3_font.pdf';

    public function testGeneratesPdfWithType3Font(): void
    {
        $writer = new PdfWriter();
        $page = $writer->addPage(612, 792);

        // -----------------------------------------------------------------
        // Define two glyph procedures: 'square' and 'triangle'.
        // Each uses the d1 operator: wx wy llx lly urx ury d1, then paints.
        // -----------------------------------------------------------------
        $squareProc = new ContentStream();
        $squareProc->setGlyphWidthAndBoundingBox(700, 0, 0, 0, 700, 700)
            ->rectangle(50, 50, 600, 600)
            ->fill();
        $squareRef = $writer->register($squareProc);

        $triangleProc = new ContentStream();
        $triangleProc->setGlyphWidthAndBoundingBox(700, 0, 0, 0, 700, 700)
            ->moveTo(350, 650)
            ->lineTo(50, 50)
            ->lineTo(650, 50)
            ->closePath()
            ->fill();
        $triangleRef = $writer->register($triangleProc);

        // -----------------------------------------------------------------
        // Encoding: maps byte 65 ('A') → 'square', 66 ('B') → 'triangle'.
        // -----------------------------------------------------------------
        $encoding = new Encoding();
        $encoding->differences = new PdfArray([
            new PdfNumber(65),
            new PdfName('square'),
            new PdfName('triangle'),
        ]);
        $encodingRef = $writer->register($encoding);

        // -----------------------------------------------------------------
        // Type 3 font itself.
        // -----------------------------------------------------------------
        $font = new Type3Font('DemoType3');
        $font->fontBBox = new PdfArray([
            new PdfNumber(0), new PdfNumber(0),
            new PdfNumber(700), new PdfNumber(700),
        ]);
        $font->firstChar = 65;
        $font->lastChar = 66;
        $font->widths = new PdfArray([
            new PdfNumber(700),
            new PdfNumber(700),
        ]);
        $font->encoding = $encodingRef;
        $font->addCharProc('square', $squareRef);
        $font->addCharProc('triangle', $triangleRef);
        // Minimal Resources dict with ProcSet so the glyph streams can paint.
        $font->resources = new PdfDictionary([
            'ProcSet' => new PdfArray([new PdfName('PDF')]),
        ]);

        $fontName = $writer->addFont($font)->getResourceName();

        // -----------------------------------------------------------------
        // Page content: paint 'AB' using the Type 3 font at 40pt.
        // -----------------------------------------------------------------
        $cs = $writer->addContentStream($page);
        $cs->beginText()
            ->setFont($fontName, 40)
            ->moveTextPosition(72, 700)
            ->showText('AB')
            ->endText();

        $writer->save(self::OUTPUT_FILE);

        self::assertFileExists(self::OUTPUT_FILE);
        $this->assertQpdfValid(self::OUTPUT_FILE);
        $content = file_get_contents(self::OUTPUT_FILE);
        self::assertNotFalse($content);
        self::assertStringStartsWith('%PDF-', $content);
        self::assertStringContainsString('/Subtype /Type3', $content);
        self::assertStringContainsString('/CharProcs', $content);
        self::assertStringContainsString('/FontMatrix', $content);
        self::assertStringContainsString('%%EOF', $content);
    }
}
