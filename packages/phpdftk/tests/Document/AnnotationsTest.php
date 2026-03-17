<?php

declare(strict_types=1);

namespace Phpdftk\Tests\Document;

use PHPUnit\Framework\TestCase;
use Phpdftk\Annotation\HighlightAnnotation;
use Phpdftk\Annotation\LinkAnnotation;
use Phpdftk\Annotation\StampAnnotation;
use Phpdftk\Annotation\TextAnnotation;
use Phpdftk\Core\PdfArray;
use Phpdftk\Core\PdfName;
use Phpdftk\Core\PdfNumber;
use Phpdftk\Core\PdfReference;
use Phpdftk\Core\PdfString;
use Phpdftk\Font\StandardFont;
use Phpdftk\Font\Type1Font;
use Phpdftk\Writer\PdfWriter;

/**
 * Generates a PDF with various annotation types and verifies validity.
 */
class AnnotationsTest extends TestCase
{
    private const OUTPUT_FILE = __DIR__ . '/../output/annotations.pdf';

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
            ])
        );
        $textAnnot->contents   = new PdfString('This is a sticky note annotation.');
        $textAnnot->name       = new PdfName('Note');
        $textAnnot->open       = false;
        $textAnnot->c          = new PdfArray([new PdfNumber(1), new PdfNumber(1), new PdfNumber(0)]); // yellow

        $writer->register($textAnnot);
        $page->annots[] = new PdfReference($textAnnot->objectNumber);

        // ----------------------------------------------------------------
        // Link annotation pointing to page 1 (local destination)
        // ----------------------------------------------------------------
        $linkAnnot = new LinkAnnotation(
            new PdfArray([
                new PdfNumber(72),
                new PdfNumber(600),
                new PdfNumber(250),
                new PdfNumber(625),
            ])
        );
        $linkAnnot->h = new PdfName('I'); // Invert highlight mode

        $writer->register($linkAnnot);
        $page->annots[] = new PdfReference($linkAnnot->objectNumber);

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
            ])
        );
        $highlightAnnot->contents = new PdfString('Highlighted text');
        $highlightAnnot->c = new PdfArray([new PdfNumber(1), new PdfNumber(1), new PdfNumber(0)]);

        $writer->register($highlightAnnot);
        $page->annots[] = new PdfReference($highlightAnnot->objectNumber);

        // ----------------------------------------------------------------
        // Stamp annotation
        // ----------------------------------------------------------------
        $stampAnnot = new StampAnnotation(
            new PdfArray([
                new PdfNumber(72),
                new PdfNumber(400),
                new PdfNumber(272),
                new PdfNumber(450),
            ])
        );
        $stampAnnot->name     = new PdfName('Approved');
        $stampAnnot->contents = new PdfString('Document approved');

        $writer->register($stampAnnot);
        $page->annots[] = new PdfReference($stampAnnot->objectNumber);

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
