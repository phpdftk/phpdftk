<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Tests\Document;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Phpdftk\Pdf\Core\Annotation\HighlightAnnotation;
use Phpdftk\Pdf\Core\Annotation\LinkAnnotation;
use Phpdftk\Pdf\Core\Annotation\StampAnnotation;
use Phpdftk\Pdf\Core\Annotation\TextAnnotation;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfString;
use Phpdftk\Pdf\Core\Font\StandardFont;
use Phpdftk\Pdf\Core\Font\Type1Font;
use Phpdftk\Pdf\Writer\PdfWriter;
use Phpdftk\Tests\Support\QpdfValidationTrait;

/**
 * Generates a PDF with various annotation types and verifies validity.
 */
#[Group("qpdf")]
class AnnotationsTest extends TestCase
{
    use QpdfValidationTrait;
    private const OUTPUT_FILE = __DIR__ . '/../../../../../docs/sample-pdfs/annotations.pdf';

    public function testGeneratesAnnotationsPdf(): void
    {
        $writer = new PdfWriter();
        $page   = $writer->addPage(612, 792);
        $writer->addFont(new Type1Font(StandardFont::Helvetica));

        // ----------------------------------------------------------------
        // Text annotation (sticky note)
        // ----------------------------------------------------------------
        $textAnnot = new TextAnnotation(
            new PdfArray([
                new PdfNumber(72),
                new PdfNumber(700),
                new PdfNumber(120),
                new PdfNumber(740),
            ]),
        );
        $textAnnot->contents   = new PdfString('This is a sticky note annotation.');
        $textAnnot->name       = new PdfName('Note');
        $textAnnot->open       = false;
        $textAnnot->c          = new PdfArray([new PdfNumber(1), new PdfNumber(1), new PdfNumber(0)]); // yellow

        $writer->register($textAnnot);
        $page->corePage()->annots[] = new PdfReference($textAnnot->objectNumber);

        // ----------------------------------------------------------------
        // Link annotation pointing to page 1 (local destination)
        // ----------------------------------------------------------------
        $linkAnnot = new LinkAnnotation(
            new PdfArray([
                new PdfNumber(72),
                new PdfNumber(600),
                new PdfNumber(250),
                new PdfNumber(625),
            ]),
        );
        $linkAnnot->h = new PdfName('I'); // Invert highlight mode

        $writer->register($linkAnnot);
        $page->corePage()->annots[] = new PdfReference($linkAnnot->objectNumber);

        // ----------------------------------------------------------------
        // Highlight annotation
        // ----------------------------------------------------------------
        $highlightAnnot = new HighlightAnnotation(
            new PdfArray([
                new PdfNumber(72),
                new PdfNumber(500),
                new PdfNumber(300),
                new PdfNumber(515),
            ]),
            new PdfArray([
                new PdfNumber(72),
                new PdfNumber(515),
                new PdfNumber(300),
                new PdfNumber(515),
                new PdfNumber(72),
                new PdfNumber(500),
                new PdfNumber(300),
                new PdfNumber(500),
            ]),
        );
        $highlightAnnot->contents = new PdfString('Highlighted text');
        $highlightAnnot->c = new PdfArray([new PdfNumber(1), new PdfNumber(1), new PdfNumber(0)]);

        $writer->register($highlightAnnot);
        $page->corePage()->annots[] = new PdfReference($highlightAnnot->objectNumber);

        // ----------------------------------------------------------------
        // Stamp annotation
        // ----------------------------------------------------------------
        $stampAnnot = new StampAnnotation(
            new PdfArray([
                new PdfNumber(72),
                new PdfNumber(400),
                new PdfNumber(272),
                new PdfNumber(450),
            ]),
        );
        $stampAnnot->name     = new PdfName('Approved');
        $stampAnnot->contents = new PdfString('Document approved');

        $writer->register($stampAnnot);
        $page->corePage()->annots[] = new PdfReference($stampAnnot->objectNumber);

        // Add some text content to the page as well
        $cs = $writer->addContentStream($page);
        $cs->beginText()
           ->setFont('F1', 12)
           ->moveTextPosition(72, 760)
           ->showText('Annotations Test Page')
           ->endText();

        // ----------------------------------------------------------------
        // Save and validate
        // ----------------------------------------------------------------
        $writer->save(self::OUTPUT_FILE);

        self::assertFileExists(self::OUTPUT_FILE);
        $this->assertQpdfValid(self::OUTPUT_FILE);

        $content = file_get_contents(self::OUTPUT_FILE);
        self::assertNotFalse($content);
        self::assertStringStartsWith('%PDF-', $content);
        self::assertStringContainsString('/Subtype /Text', $content);
        self::assertStringContainsString('/Subtype /Link', $content);
        self::assertStringContainsString('/Subtype /Highlight', $content);
        self::assertStringContainsString('/Subtype /Stamp', $content);
        self::assertStringContainsString('/Annots', $content);
        self::assertStringContainsString('%%EOF', $content);
    }
}
