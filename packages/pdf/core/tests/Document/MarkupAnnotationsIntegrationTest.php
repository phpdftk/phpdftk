<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Tests\Document;

use ApprLabs\Pdf\Core\Annotation\FreeTextAnnotation;
use ApprLabs\Pdf\Core\Annotation\HighlightAnnotation;
use ApprLabs\Pdf\Core\Annotation\PopupAnnotation;
use ApprLabs\Pdf\Core\Annotation\TextAnnotation;
use ApprLabs\Pdf\Core\Font\StandardFont;
use ApprLabs\Pdf\Core\Font\Type1Font;
use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\PdfString;
use ApprLabs\Pdf\Writer\PdfWriter;
use ApprLabs\Tests\Support\QpdfValidationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end: generates a real PDF with a threaded comment (Text →
 * Popup) and a Highlight-in-reply-to, exercising the full markup
 * annotation field set (/T, /Subj, /CreationDate, /IRT, /RT, /Popup).
 */
#[Group("qpdf")]
class MarkupAnnotationsIntegrationTest extends TestCase
{
    use QpdfValidationTrait;

    private const OUTPUT_FILE = __DIR__ . '/../../../../../docs/sample-pdfs/markup_annotations.pdf';

    public function testGeneratesPdfWithMarkupFields(): void
    {
        $writer = new PdfWriter();
        $page = $writer->addPage(612, 792);
        $fontName = $writer->addFont(new Type1Font(StandardFont::Helvetica))->getResourceName();

        $cs = $writer->addContentStream($page);
        $cs->beginText()
            ->setFont($fontName, 18)
            ->moveTextPosition(72, 740)
            ->showText('Markup annotations')
            ->moveTextPosition(0, -24)
            ->setFont($fontName, 11)
            ->showText('Comment thread + highlight reply.')
            ->endText();

        // --- Popup associated with the sticky note ------------------
        $popup = new PopupAnnotation(new PdfArray([
            new PdfNumber(400), new PdfNumber(600),
            new PdfNumber(540), new PdfNumber(720),
        ]));
        $popupRef = $writer->register($popup);
        $page->corePage()->annots[] = $popupRef;

        // --- Root comment (sticky note) -----------------------------
        $note = new TextAnnotation(new PdfArray([
            new PdfNumber(100), new PdfNumber(690),
            new PdfNumber(120), new PdfNumber(710),
        ]));
        $note->contents = new PdfString('Please review this section');
        $note->t = new PdfString('Alice');
        $note->subj = new PdfString('Review request');
        $note->creationDate = new PdfString('D:20260411120000Z');
        $note->m = new PdfString('D:20260411120000Z');
        $note->popup = $popupRef;
        $noteRef = $writer->register($note);
        $page->corePage()->annots[] = $noteRef;

        // Wire popup parent back to the note.
        $popup->parent = $noteRef;

        // --- Bob replies with a highlight ---------------------------
        $highlight = new HighlightAnnotation(
            new PdfArray([
                new PdfNumber(72), new PdfNumber(500),
                new PdfNumber(540), new PdfNumber(520),
            ]),
            new PdfArray([
                new PdfNumber(72), new PdfNumber(520),
                new PdfNumber(540), new PdfNumber(520),
                new PdfNumber(540), new PdfNumber(500),
                new PdfNumber(72), new PdfNumber(500),
            ])
        );
        $highlight->contents = new PdfString('I agree, this is important');
        $highlight->t = new PdfString('Bob');
        $highlight->subj = new PdfString('Agreed');
        $highlight->creationDate = new PdfString('D:20260411130000Z');
        $highlight->irt = $noteRef;
        $highlight->rt = new PdfName('R');
        $highlight->markupCa = 0.4;
        $page->corePage()->annots[] = $writer->register($highlight);

        // --- Callout FreeText annotation ----------------------------
        $callout = new FreeTextAnnotation(
            new PdfArray([
                new PdfNumber(200), new PdfNumber(300),
                new PdfNumber(400), new PdfNumber(400),
            ]),
            new PdfString('0 0 0 rg /Helv 12 Tf')
        );
        $callout->contents = new PdfString('Callout text');
        $callout->subj = new PdfString('Callout');
        $callout->it = new PdfName('FreeTextCallout');
        $callout->t = new PdfString('Alice');
        $page->corePage()->annots[] = $writer->register($callout);

        $writer->save(self::OUTPUT_FILE);

        $pdf = file_get_contents(self::OUTPUT_FILE);
        self::assertNotFalse($pdf);
        self::assertStringStartsWith('%PDF-', $pdf);
        $this->assertQpdfValid(self::OUTPUT_FILE);
        self::assertStringContainsString('/Subtype /Text', $pdf);
        self::assertStringContainsString('/Subtype /Highlight', $pdf);
        self::assertStringContainsString('/Subtype /FreeText', $pdf);
        self::assertStringContainsString('/T (Alice)', $pdf);
        self::assertStringContainsString('/T (Bob)', $pdf);
        self::assertStringContainsString('/Subj (Review request)', $pdf);
        self::assertStringContainsString('/CreationDate', $pdf);
        self::assertStringContainsString('/IRT', $pdf);
        self::assertStringContainsString('/RT /R', $pdf);
        self::assertStringContainsString('/IT /FreeTextCallout', $pdf);
        self::assertStringContainsString('/Popup', $pdf);
        self::assertStringContainsString('%%EOF', $pdf);
    }
}
