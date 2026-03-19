<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Tests\Annotation;

use PHPUnit\Framework\TestCase;
use ApprLabs\Pdf\Core\Annotation\FreeTextAnnotation;
use ApprLabs\Pdf\Core\Annotation\HighlightAnnotation;
use ApprLabs\Pdf\Core\Annotation\InkAnnotation;
use ApprLabs\Pdf\Core\Annotation\LinkAnnotation;
use ApprLabs\Pdf\Core\Annotation\PopupAnnotation;
use ApprLabs\Pdf\Core\Annotation\StampAnnotation;
use ApprLabs\Pdf\Core\Annotation\TextAnnotation;
use ApprLabs\Pdf\Core\Annotation\WidgetAnnotation;
use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\PdfReference;
use ApprLabs\Pdf\Core\PdfString;

class AnnotationTest extends TestCase
{
    private function makeRect(): PdfArray
    {
        return new PdfArray([
            new PdfNumber(10),
            new PdfNumber(10),
            new PdfNumber(200),
            new PdfNumber(50),
        ]);
    }

    // -----------------------------------------------------------------------
    // TextAnnotation
    // -----------------------------------------------------------------------

    public function testTextAnnotationSubtype(): void
    {
        $annot = new TextAnnotation($this->makeRect());
        $annot->objectNumber = 1;
        self::assertSame('Text', $annot->getSubtype());
        self::assertStringContainsString('/Subtype /Text', $annot->toPdf());
    }

    public function testTextAnnotationType(): void
    {
        $annot = new TextAnnotation($this->makeRect());
        $annot->objectNumber = 1;
        self::assertStringContainsString('/Type /Annot', $annot->toPdf());
    }

    public function testTextAnnotationContents(): void
    {
        $annot = new TextAnnotation($this->makeRect());
        $annot->objectNumber = 1;
        $annot->contents = new PdfString('Hello annotation');
        self::assertStringContainsString('/Contents', $annot->toPdf());
    }

    public function testTextAnnotationOpen(): void
    {
        $annot = new TextAnnotation($this->makeRect());
        $annot->objectNumber = 1;
        $annot->open = true;
        self::assertStringContainsString('/Open true', $annot->toPdf());
    }

    public function testTextAnnotationName(): void
    {
        $annot = new TextAnnotation($this->makeRect());
        $annot->objectNumber = 1;
        $annot->name = new PdfName('Note');
        self::assertStringContainsString('/Name /Note', $annot->toPdf());
    }

    public function testTextAnnotationColor(): void
    {
        $annot = new TextAnnotation($this->makeRect());
        $annot->objectNumber = 1;
        $annot->c = new PdfArray([new PdfNumber(1), new PdfNumber(1), new PdfNumber(0)]);
        self::assertStringContainsString('/C', $annot->toPdf());
    }

    public function testTextAnnotationWithPageRef(): void
    {
        $annot = new TextAnnotation($this->makeRect());
        $annot->objectNumber = 1;
        $annot->p = new PdfReference(3);
        self::assertStringContainsString('/P 3 0 R', $annot->toPdf());
    }

    public function testTextAnnotationFlags(): void
    {
        $annot = new TextAnnotation($this->makeRect());
        $annot->objectNumber = 1;
        $annot->f = 4;
        self::assertStringContainsString('/F 4', $annot->toPdf());
    }

    // -----------------------------------------------------------------------
    // LinkAnnotation
    // -----------------------------------------------------------------------

    public function testLinkAnnotationSubtype(): void
    {
        $annot = new LinkAnnotation($this->makeRect());
        $annot->objectNumber = 1;
        self::assertSame('Link', $annot->getSubtype());
        self::assertStringContainsString('/Subtype /Link', $annot->toPdf());
    }

    public function testLinkAnnotationWithDest(): void
    {
        $annot = new LinkAnnotation($this->makeRect());
        $annot->objectNumber = 1;
        $annot->dest = new PdfReference(5);
        self::assertStringContainsString('/Dest 5 0 R', $annot->toPdf());
    }

    public function testLinkAnnotationHighlightMode(): void
    {
        $annot = new LinkAnnotation($this->makeRect());
        $annot->objectNumber = 1;
        $annot->h = new PdfName('I');
        self::assertStringContainsString('/H /I', $annot->toPdf());
    }

    // -----------------------------------------------------------------------
    // HighlightAnnotation
    // -----------------------------------------------------------------------

    public function testHighlightAnnotationSubtype(): void
    {
        $qp = new PdfArray([
            new PdfNumber(10), new PdfNumber(50),
            new PdfNumber(200), new PdfNumber(50),
            new PdfNumber(10), new PdfNumber(40),
            new PdfNumber(200), new PdfNumber(40),
        ]);
        $annot = new HighlightAnnotation($this->makeRect(), $qp);
        $annot->objectNumber = 1;
        self::assertSame('Highlight', $annot->getSubtype());
        self::assertStringContainsString('/Subtype /Highlight', $annot->toPdf());
        self::assertStringContainsString('/QuadPoints', $annot->toPdf());
    }

    // -----------------------------------------------------------------------
    // FreeTextAnnotation
    // -----------------------------------------------------------------------

    public function testFreeTextAnnotationSubtype(): void
    {
        $annot = new FreeTextAnnotation($this->makeRect(), new PdfString('/Helvetica 12 Tf 0 g'));
        $annot->objectNumber = 1;
        self::assertSame('FreeText', $annot->getSubtype());
        self::assertStringContainsString('/Subtype /FreeText', $annot->toPdf());
        self::assertStringContainsString('/DA', $annot->toPdf());
    }

    public function testFreeTextAnnotationJustification(): void
    {
        $annot = new FreeTextAnnotation($this->makeRect(), new PdfString('/Helvetica 12 Tf 0 g'));
        $annot->objectNumber = 1;
        $annot->q = 1; // center
        self::assertStringContainsString('/Q 1', $annot->toPdf());
    }

    // -----------------------------------------------------------------------
    // StampAnnotation
    // -----------------------------------------------------------------------

    public function testStampAnnotationSubtype(): void
    {
        $annot = new StampAnnotation($this->makeRect());
        $annot->objectNumber = 1;
        self::assertSame('Stamp', $annot->getSubtype());
        self::assertStringContainsString('/Subtype /Stamp', $annot->toPdf());
    }

    public function testStampAnnotationName(): void
    {
        $annot = new StampAnnotation($this->makeRect());
        $annot->objectNumber = 1;
        $annot->name = new PdfName('Approved');
        self::assertStringContainsString('/Name /Approved', $annot->toPdf());
    }

    // -----------------------------------------------------------------------
    // InkAnnotation
    // -----------------------------------------------------------------------

    public function testInkAnnotationSubtype(): void
    {
        $inkList = new PdfArray([
            new PdfArray([
                new PdfNumber(100), new PdfNumber(200),
                new PdfNumber(150), new PdfNumber(250),
            ]),
        ]);
        $annot = new InkAnnotation($this->makeRect(), $inkList);
        $annot->objectNumber = 1;
        self::assertSame('Ink', $annot->getSubtype());
        self::assertStringContainsString('/Subtype /Ink', $annot->toPdf());
        self::assertStringContainsString('/InkList', $annot->toPdf());
    }

    // -----------------------------------------------------------------------
    // PopupAnnotation
    // -----------------------------------------------------------------------

    public function testPopupAnnotationSubtype(): void
    {
        $annot = new PopupAnnotation($this->makeRect());
        $annot->objectNumber = 1;
        self::assertSame('Popup', $annot->getSubtype());
        self::assertStringContainsString('/Subtype /Popup', $annot->toPdf());
    }

    public function testPopupAnnotationOpen(): void
    {
        $annot = new PopupAnnotation($this->makeRect());
        $annot->objectNumber = 1;
        $annot->open = false;
        self::assertStringContainsString('/Open false', $annot->toPdf());
    }

    public function testPopupAnnotationParent(): void
    {
        $annot = new PopupAnnotation($this->makeRect());
        $annot->objectNumber = 1;
        $annot->parent = new PdfReference(7);
        self::assertStringContainsString('/Parent 7 0 R', $annot->toPdf());
    }

    // -----------------------------------------------------------------------
    // WidgetAnnotation
    // -----------------------------------------------------------------------

    public function testWidgetAnnotationSubtype(): void
    {
        $annot = new WidgetAnnotation($this->makeRect());
        $annot->objectNumber = 1;
        self::assertSame('Widget', $annot->getSubtype());
        self::assertStringContainsString('/Subtype /Widget', $annot->toPdf());
    }

    public function testWidgetAnnotationHighlight(): void
    {
        $annot = new WidgetAnnotation($this->makeRect());
        $annot->objectNumber = 1;
        $annot->h = new PdfName('P');
        self::assertStringContainsString('/H /P', $annot->toPdf());
    }

    public function testWidgetAnnotationParent(): void
    {
        $annot = new WidgetAnnotation($this->makeRect());
        $annot->objectNumber = 1;
        $annot->parent = new PdfReference(10);
        self::assertStringContainsString('/Parent 10 0 R', $annot->toPdf());
    }

    // -----------------------------------------------------------------------
    // Base Annotation
    // -----------------------------------------------------------------------

    public function testAnnotationBorder(): void
    {
        $annot = new TextAnnotation($this->makeRect());
        $annot->objectNumber = 1;
        $annot->border = new PdfArray([
            new PdfNumber(0), new PdfNumber(0), new PdfNumber(1),
        ]);
        self::assertStringContainsString('/Border', $annot->toPdf());
    }

    public function testAnnotationRect(): void
    {
        $rect = $this->makeRect();
        $annot = new TextAnnotation($rect);
        $annot->objectNumber = 1;
        self::assertStringContainsString('/Rect', $annot->toPdf());
    }

    public function testAnnotationToIndirectObject(): void
    {
        $annot = new TextAnnotation($this->makeRect());
        $annot->objectNumber = 5;
        $annot->generationNumber = 0;
        $indirect = $annot->toIndirectObject();
        self::assertStringContainsString('5 0 obj', $indirect);
        self::assertStringContainsString('endobj', $indirect);
    }
}
