<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Tests\Annotation;

use PHPUnit\Framework\TestCase;
use Phpdftk\Pdf\Core\Annotation\BorderEffect;
use Phpdftk\Pdf\Core\Annotation\CaretAnnotation;
use Phpdftk\Pdf\Core\Annotation\CircleAnnotation;
use Phpdftk\Pdf\Core\Annotation\FileAttachmentAnnotation;
use Phpdftk\Pdf\Core\Annotation\FreeTextAnnotation;
use Phpdftk\Pdf\Core\Annotation\HighlightAnnotation;
use Phpdftk\Pdf\Core\Annotation\InkAnnotation;
use Phpdftk\Pdf\Core\Annotation\LineAnnotation;
use Phpdftk\Pdf\Core\Annotation\LinkAnnotation;
use Phpdftk\Pdf\Core\Annotation\MovieAnnotation;
use Phpdftk\Pdf\Core\Annotation\PolyLineAnnotation;
use Phpdftk\Pdf\Core\Annotation\PolygonAnnotation;
use Phpdftk\Pdf\Core\Annotation\PopupAnnotation;
use Phpdftk\Pdf\Core\Annotation\PrinterMarkAnnotation;
use Phpdftk\Pdf\Core\Annotation\ProjectionAnnotation;
use Phpdftk\Pdf\Core\Annotation\RedactAnnotation;
use Phpdftk\Pdf\Core\Annotation\RichMediaAnnotation;
use Phpdftk\Pdf\Core\Annotation\ScreenAnnotation;
use Phpdftk\Pdf\Core\Annotation\SoundAnnotation;
use Phpdftk\Pdf\Core\Annotation\SquareAnnotation;
use Phpdftk\Pdf\Core\Annotation\SquigglyAnnotation;
use Phpdftk\Pdf\Core\Annotation\StampAnnotation;
use Phpdftk\Pdf\Core\Annotation\StrikeOutAnnotation;
use Phpdftk\Pdf\Core\Annotation\TextAnnotation;
use Phpdftk\Pdf\Core\Annotation\ThreeDAnnotation;
use Phpdftk\Pdf\Core\Annotation\TrapNetAnnotation;
use Phpdftk\Pdf\Core\Annotation\UnderlineAnnotation;
use Phpdftk\Pdf\Core\Annotation\WatermarkAnnotation;
use Phpdftk\Pdf\Core\Annotation\WidgetAnnotation;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfString;

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

    // -----------------------------------------------------------------------
    // Base Annotation — new fields
    // -----------------------------------------------------------------------

    public function testAnnotationAf(): void
    {
        $annot = new TextAnnotation($this->makeRect());
        $annot->objectNumber = 1;
        $annot->af = new PdfArray([new PdfReference(10)]);
        self::assertStringContainsString('/AF', $annot->toPdf());
    }

    public function testAnnotationCa(): void
    {
        $annot = new TextAnnotation($this->makeRect());
        $annot->objectNumber = 1;
        $annot->ca = new PdfNumber(0.5);
        self::assertStringContainsString('/ca 0.5', $annot->toPdf());
    }

    public function testAnnotationBm(): void
    {
        $annot = new TextAnnotation($this->makeRect());
        $annot->objectNumber = 1;
        $annot->bm = new PdfName('Multiply');
        self::assertStringContainsString('/BM /Multiply', $annot->toPdf());
    }

    public function testAnnotationLang(): void
    {
        $annot = new TextAnnotation($this->makeRect());
        $annot->objectNumber = 1;
        $annot->lang = new PdfString('en-US');
        self::assertStringContainsString('/Lang', $annot->toPdf());
    }

    // -----------------------------------------------------------------------
    // UnderlineAnnotation
    // -----------------------------------------------------------------------

    public function testUnderlineAnnotation(): void
    {
        $annot = new UnderlineAnnotation($this->makeRect());
        $annot->objectNumber = 1;
        $annot->quadPoints = new PdfArray([
            new PdfNumber(0), new PdfNumber(0),
            new PdfNumber(100), new PdfNumber(0),
            new PdfNumber(0), new PdfNumber(10),
            new PdfNumber(100), new PdfNumber(10),
        ]);
        $pdf = $annot->toPdf();
        self::assertStringContainsString('/Subtype /Underline', $pdf);
        self::assertStringContainsString('/QuadPoints', $pdf);
    }

    // -----------------------------------------------------------------------
    // SquigglyAnnotation
    // -----------------------------------------------------------------------

    public function testSquigglyAnnotation(): void
    {
        $annot = new SquigglyAnnotation($this->makeRect());
        $annot->objectNumber = 1;
        $annot->quadPoints = new PdfArray([
            new PdfNumber(0), new PdfNumber(0),
            new PdfNumber(100), new PdfNumber(0),
            new PdfNumber(0), new PdfNumber(10),
            new PdfNumber(100), new PdfNumber(10),
        ]);
        $pdf = $annot->toPdf();
        self::assertStringContainsString('/Subtype /Squiggly', $pdf);
    }

    // -----------------------------------------------------------------------
    // StrikeOutAnnotation
    // -----------------------------------------------------------------------

    public function testStrikeOutAnnotation(): void
    {
        $annot = new StrikeOutAnnotation($this->makeRect());
        $annot->objectNumber = 1;
        $annot->quadPoints = new PdfArray([
            new PdfNumber(0), new PdfNumber(0),
            new PdfNumber(100), new PdfNumber(0),
            new PdfNumber(0), new PdfNumber(10),
            new PdfNumber(100), new PdfNumber(10),
        ]);
        $pdf = $annot->toPdf();
        self::assertStringContainsString('/Subtype /StrikeOut', $pdf);
    }

    // -----------------------------------------------------------------------
    // LineAnnotation
    // -----------------------------------------------------------------------

    public function testLineAnnotation(): void
    {
        $annot = new LineAnnotation($this->makeRect());
        $annot->objectNumber = 1;
        $annot->l = new PdfArray([
            new PdfNumber(10), new PdfNumber(20),
            new PdfNumber(300), new PdfNumber(400),
        ]);
        $annot->le = new PdfArray([new PdfName('OpenArrow'), new PdfName('None')]);
        $pdf = $annot->toPdf();
        self::assertStringContainsString('/Subtype /Line', $pdf);
        self::assertStringContainsString('/L', $pdf);
        self::assertStringContainsString('/LE', $pdf);
    }

    public function testLineAnnotationAllFields(): void
    {
        $annot = new LineAnnotation($this->makeRect());
        $annot->objectNumber = 1;
        $annot->l = new PdfArray([
            new PdfNumber(10), new PdfNumber(20),
            new PdfNumber(300), new PdfNumber(400),
        ]);
        $annot->le = new PdfArray([new PdfName('OpenArrow'), new PdfName('None')]);
        $annot->ic = new PdfArray([new PdfNumber(1), new PdfNumber(0), new PdfNumber(0)]);
        $annot->ll = new PdfNumber(10);
        $annot->lle = new PdfNumber(5);
        $annot->cap = true;
        $annot->it = new PdfName('LineDimension');
        $annot->llo = new PdfNumber(3);
        $annot->cp = new PdfName('Top');
        $annot->measure = new PdfReference(20);
        $annot->co = new PdfArray([new PdfNumber(0), new PdfNumber(-10)]);
        $pdf = $annot->toPdf();
        self::assertStringContainsString('/L', $pdf);
        self::assertStringContainsString('/LE', $pdf);
        self::assertStringContainsString('/IC', $pdf);
        self::assertStringContainsString('/LL ', $pdf);
        self::assertStringContainsString('/LLE', $pdf);
        self::assertStringContainsString('/Cap true', $pdf);
        self::assertStringContainsString('/IT /LineDimension', $pdf);
        self::assertStringContainsString('/LLO', $pdf);
        self::assertStringContainsString('/CP /Top', $pdf);
        self::assertStringContainsString('/Measure 20 0 R', $pdf);
        self::assertStringContainsString('/CO', $pdf);
    }

    // -----------------------------------------------------------------------
    // SquareAnnotation
    // -----------------------------------------------------------------------

    public function testSquareAnnotation(): void
    {
        $annot = new SquareAnnotation($this->makeRect());
        $annot->objectNumber = 1;
        $annot->ic = new PdfArray([new PdfNumber(0), new PdfNumber(1), new PdfNumber(0)]);
        $pdf = $annot->toPdf();
        self::assertStringContainsString('/Subtype /Square', $pdf);
        self::assertStringContainsString('/IC', $pdf);
    }

    // -----------------------------------------------------------------------
    // CircleAnnotation
    // -----------------------------------------------------------------------

    public function testCircleAnnotation(): void
    {
        $annot = new CircleAnnotation($this->makeRect());
        $annot->objectNumber = 1;
        $annot->ic = new PdfArray([new PdfNumber(0), new PdfNumber(0), new PdfNumber(1)]);
        $be = new BorderEffect();
        $be->s = new PdfName('C');
        $be->i = new PdfNumber(2);
        $annot->be = $be;
        $pdf = $annot->toPdf();
        self::assertStringContainsString('/Subtype /Circle', $pdf);
        self::assertStringContainsString('/IC', $pdf);
        self::assertStringContainsString('/BE', $pdf);
    }

    // -----------------------------------------------------------------------
    // PolygonAnnotation
    // -----------------------------------------------------------------------

    public function testPolygonAnnotation(): void
    {
        $annot = new PolygonAnnotation($this->makeRect());
        $annot->objectNumber = 1;
        $annot->vertices = new PdfArray([
            new PdfNumber(10), new PdfNumber(10),
            new PdfNumber(50), new PdfNumber(80),
            new PdfNumber(90), new PdfNumber(10),
        ]);
        $pdf = $annot->toPdf();
        self::assertStringContainsString('/Subtype /Polygon', $pdf);
        self::assertStringContainsString('/Vertices', $pdf);
    }

    // -----------------------------------------------------------------------
    // PolyLineAnnotation
    // -----------------------------------------------------------------------

    public function testPolyLineAnnotation(): void
    {
        $annot = new PolyLineAnnotation($this->makeRect());
        $annot->objectNumber = 1;
        $annot->vertices = new PdfArray([
            new PdfNumber(10), new PdfNumber(10),
            new PdfNumber(50), new PdfNumber(80),
            new PdfNumber(90), new PdfNumber(10),
        ]);
        $pdf = $annot->toPdf();
        self::assertStringContainsString('/Subtype /PolyLine', $pdf);
    }

    // -----------------------------------------------------------------------
    // CaretAnnotation
    // -----------------------------------------------------------------------

    public function testCaretAnnotation(): void
    {
        $annot = new CaretAnnotation($this->makeRect());
        $annot->objectNumber = 1;
        $annot->sy = new PdfName('P');
        $pdf = $annot->toPdf();
        self::assertStringContainsString('/Subtype /Caret', $pdf);
        self::assertStringContainsString('/Sy /P', $pdf);
    }

    // -----------------------------------------------------------------------
    // FileAttachmentAnnotation
    // -----------------------------------------------------------------------

    public function testFileAttachmentAnnotation(): void
    {
        $annot = new FileAttachmentAnnotation($this->makeRect());
        $annot->objectNumber = 1;
        $annot->fs = new PdfReference(15);
        $annot->name = new PdfName('Paperclip');
        $pdf = $annot->toPdf();
        self::assertStringContainsString('/Subtype /FileAttachment', $pdf);
        self::assertStringContainsString('/FS 15 0 R', $pdf);
        self::assertStringContainsString('/Name /Paperclip', $pdf);
    }

    // -----------------------------------------------------------------------
    // SoundAnnotation
    // -----------------------------------------------------------------------

    public function testSoundAnnotation(): void
    {
        $annot = new SoundAnnotation($this->makeRect());
        $annot->objectNumber = 1;
        $annot->sound = new PdfReference(20);
        $annot->name = new PdfName('Speaker');
        $pdf = $annot->toPdf();
        self::assertStringContainsString('/Subtype /Sound', $pdf);
        self::assertStringContainsString('/Sound 20 0 R', $pdf);
        self::assertStringContainsString('/Name /Speaker', $pdf);
    }

    // -----------------------------------------------------------------------
    // WatermarkAnnotation
    // -----------------------------------------------------------------------

    public function testWatermarkAnnotation(): void
    {
        $annot = new WatermarkAnnotation($this->makeRect());
        $annot->objectNumber = 1;
        $fp = new PdfDictionary();
        $fp->set('Matrix', new PdfArray([
            new PdfNumber(1), new PdfNumber(0), new PdfNumber(0),
            new PdfNumber(1), new PdfNumber(0), new PdfNumber(0),
        ]));
        $annot->fixedPrint = $fp;
        $pdf = $annot->toPdf();
        self::assertStringContainsString('/Subtype /Watermark', $pdf);
        self::assertStringContainsString('/FixedPrint', $pdf);
    }

    // -----------------------------------------------------------------------
    // PrinterMarkAnnotation
    // -----------------------------------------------------------------------

    public function testPrinterMarkAnnotation(): void
    {
        $annot = new PrinterMarkAnnotation($this->makeRect());
        $annot->objectNumber = 1;
        $annot->mn = new PdfName('ColorBar');
        $pdf = $annot->toPdf();
        self::assertStringContainsString('/Subtype /PrinterMark', $pdf);
        self::assertStringContainsString('/MN /ColorBar', $pdf);
    }

    // -----------------------------------------------------------------------
    // ScreenAnnotation
    // -----------------------------------------------------------------------

    public function testScreenAnnotation(): void
    {
        $annot = new ScreenAnnotation($this->makeRect());
        $annot->objectNumber = 1;
        $annot->t = new PdfString('My Screen');
        $annot->a = new PdfReference(30);
        $pdf = $annot->toPdf();
        self::assertStringContainsString('/Subtype /Screen', $pdf);
        self::assertStringContainsString('/T', $pdf);
        self::assertStringContainsString('/A 30 0 R', $pdf);
    }

    // -----------------------------------------------------------------------
    // MovieAnnotation
    // -----------------------------------------------------------------------

    public function testMovieAnnotation(): void
    {
        $annot = new MovieAnnotation($this->makeRect());
        $annot->objectNumber = 1;
        $annot->t = new PdfString('My Movie');
        $annot->movie = new PdfReference(25);
        $pdf = $annot->toPdf();
        self::assertStringContainsString('/Subtype /Movie', $pdf);
        self::assertStringContainsString('/T', $pdf);
        self::assertStringContainsString('/Movie 25 0 R', $pdf);
    }

    // -----------------------------------------------------------------------
    // RedactAnnotation
    // -----------------------------------------------------------------------

    public function testRedactAnnotation(): void
    {
        $annot = new RedactAnnotation($this->makeRect());
        $annot->objectNumber = 1;
        $annot->quadPoints = new PdfArray([
            new PdfNumber(0), new PdfNumber(0),
            new PdfNumber(100), new PdfNumber(0),
            new PdfNumber(0), new PdfNumber(10),
            new PdfNumber(100), new PdfNumber(10),
        ]);
        $annot->ic = new PdfArray([new PdfNumber(0), new PdfNumber(0), new PdfNumber(0)]);
        $annot->overlayText = new PdfString('REDACTED');
        $pdf = $annot->toPdf();
        self::assertStringContainsString('/Subtype /Redact', $pdf);
        self::assertStringContainsString('/QuadPoints', $pdf);
        self::assertStringContainsString('/IC', $pdf);
        self::assertStringContainsString('/OverlayText', $pdf);
    }

    public function testRedactAnnotationAllFields(): void
    {
        $annot = new RedactAnnotation($this->makeRect());
        $annot->objectNumber = 1;
        $annot->quadPoints = new PdfArray([
            new PdfNumber(0), new PdfNumber(0),
            new PdfNumber(100), new PdfNumber(0),
            new PdfNumber(0), new PdfNumber(10),
            new PdfNumber(100), new PdfNumber(10),
        ]);
        $annot->ic = new PdfArray([new PdfNumber(1), new PdfNumber(1), new PdfNumber(1)]);
        $annot->ro = new PdfReference(40);
        $annot->overlayText = new PdfString('REDACTED');
        $annot->repeat = true;
        $annot->da = new PdfString('/Helvetica 12 Tf 0 g');
        $annot->q = 1;
        $pdf = $annot->toPdf();
        self::assertStringContainsString('/QuadPoints', $pdf);
        self::assertStringContainsString('/IC', $pdf);
        self::assertStringContainsString('/RO 40 0 R', $pdf);
        self::assertStringContainsString('/OverlayText', $pdf);
        self::assertStringContainsString('/Repeat true', $pdf);
        self::assertStringContainsString('/DA', $pdf);
        self::assertStringContainsString('/Q 1', $pdf);
    }

    // -----------------------------------------------------------------------
    // ThreeDAnnotation
    // -----------------------------------------------------------------------

    public function testThreeDAnnotation(): void
    {
        $annot = new ThreeDAnnotation($this->makeRect());
        $annot->objectNumber = 1;
        $annot->dd = new PdfReference(50);
        $pdf = $annot->toPdf();
        self::assertStringContainsString('/Subtype /3D', $pdf);
        self::assertStringContainsString('/3DD 50 0 R', $pdf);
    }

    // -----------------------------------------------------------------------
    // ProjectionAnnotation
    // -----------------------------------------------------------------------

    public function testProjectionAnnotation(): void
    {
        $annot = new ProjectionAnnotation($this->makeRect());
        $annot->objectNumber = 1;
        $pdf = $annot->toPdf();
        self::assertStringContainsString('/Subtype /Projection', $pdf);
    }

    // -----------------------------------------------------------------------
    // RichMediaAnnotation
    // -----------------------------------------------------------------------

    public function testRichMediaAnnotation(): void
    {
        $annot = new RichMediaAnnotation($this->makeRect());
        $annot->objectNumber = 1;
        $settings = new PdfDictionary();
        $settings->set('Type', new PdfName('RichMediaSettings'));
        $annot->richMediaSettings = $settings;
        $pdf = $annot->toPdf();
        self::assertStringContainsString('/Subtype /RichMedia', $pdf);
        self::assertStringContainsString('/RichMediaSettings', $pdf);
    }

    // -----------------------------------------------------------------------
    // TrapNetAnnotation
    // -----------------------------------------------------------------------

    public function testTrapNetAnnotation(): void
    {
        $annot = new TrapNetAnnotation($this->makeRect());
        $annot->objectNumber = 1;
        $annot->lastModified = new PdfString('D:20260101120000');
        $annot->version = new PdfNumber(1);
        $pdf = $annot->toPdf();
        self::assertStringContainsString('/Subtype /TrapNet', $pdf);
        self::assertStringContainsString('/LastModified', $pdf);
        self::assertStringContainsString('/Version 1', $pdf);
    }
}
