<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Tests\Document;

use PHPUnit\Framework\TestCase;
use ApprLabs\Pdf\Core\Annotation\CaretAnnotation;
use ApprLabs\Pdf\Core\Annotation\CircleAnnotation;
use ApprLabs\Pdf\Core\Annotation\FileAttachmentAnnotation;
use ApprLabs\Pdf\Core\Annotation\LineAnnotation;
use ApprLabs\Pdf\Core\Annotation\MovieAnnotation;
use ApprLabs\Pdf\Core\Annotation\PolyLineAnnotation;
use ApprLabs\Pdf\Core\Annotation\PolygonAnnotation;
use ApprLabs\Pdf\Core\Annotation\PrinterMarkAnnotation;
use ApprLabs\Pdf\Core\Annotation\ProjectionAnnotation;
use ApprLabs\Pdf\Core\Annotation\RedactAnnotation;
use ApprLabs\Pdf\Core\Annotation\RichMediaAnnotation;
use ApprLabs\Pdf\Core\Annotation\ScreenAnnotation;
use ApprLabs\Pdf\Core\Annotation\SoundAnnotation;
use ApprLabs\Pdf\Core\Annotation\SquareAnnotation;
use ApprLabs\Pdf\Core\Annotation\SquigglyAnnotation;
use ApprLabs\Pdf\Core\Annotation\StrikeOutAnnotation;
use ApprLabs\Pdf\Core\Annotation\ThreeDAnnotation;
use ApprLabs\Pdf\Core\Annotation\TrapNetAnnotation;
use ApprLabs\Pdf\Core\Annotation\UnderlineAnnotation;
use ApprLabs\Pdf\Core\Annotation\WatermarkAnnotation;
use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\PdfReference;
use ApprLabs\Pdf\Core\PdfString;
use ApprLabs\Pdf\Core\Font\StandardFont;
use ApprLabs\Pdf\Core\Font\Type1Font;
use ApprLabs\Pdf\Writer\PdfWriter;

/**
 * Generates a multi-page PDF exercising all new annotation subtypes and verifies validity.
 */
class AnnotationSubtypesTest extends TestCase
{
    private const OUTPUT_FILE = __DIR__ . '/../../../../../docs/sample-pdfs/annotation_subtypes.pdf';

    public function testGeneratesAnnotationSubtypesPdf(): void
    {
        $writer = new PdfWriter();
        $fontName = $writer->addFont(new Type1Font(StandardFont::Helvetica))->getResourceName();

        // ====================================================================
        // Page 1 — Text markup and shape annotations
        // ====================================================================
        $page1 = $writer->addPage(612, 792);
        $cs1 = $writer->addContentStream($page1);
        $cs1->beginText()
            ->setFont($fontName, 16)
            ->moveTextPosition(72, 750)
            ->showText('Annotation Subtypes - Page 1: Markup & Shapes')
            ->endText();

        // UnderlineAnnotation with QuadPoints
        $underline = new UnderlineAnnotation(
            new PdfArray([new PdfNumber(72), new PdfNumber(670), new PdfNumber(300), new PdfNumber(690)])
        );
        $underline->quadPoints = new PdfArray([
            new PdfNumber(72), new PdfNumber(690),
            new PdfNumber(300), new PdfNumber(690),
            new PdfNumber(72), new PdfNumber(670),
            new PdfNumber(300), new PdfNumber(670),
        ]);
        $underline->contents = new PdfString('Underlined text');
        $writer->register($underline);
        $page1->corePage()->annots[] = new PdfReference($underline->objectNumber);

        // SquigglyAnnotation with QuadPoints
        $squiggly = new SquigglyAnnotation(
            new PdfArray([new PdfNumber(72), new PdfNumber(630), new PdfNumber(300), new PdfNumber(650)])
        );
        $squiggly->quadPoints = new PdfArray([
            new PdfNumber(72), new PdfNumber(650),
            new PdfNumber(300), new PdfNumber(650),
            new PdfNumber(72), new PdfNumber(630),
            new PdfNumber(300), new PdfNumber(630),
        ]);
        $squiggly->contents = new PdfString('Squiggly text');
        $writer->register($squiggly);
        $page1->corePage()->annots[] = new PdfReference($squiggly->objectNumber);

        // StrikeOutAnnotation with QuadPoints
        $strikeOut = new StrikeOutAnnotation(
            new PdfArray([new PdfNumber(72), new PdfNumber(590), new PdfNumber(300), new PdfNumber(610)])
        );
        $strikeOut->quadPoints = new PdfArray([
            new PdfNumber(72), new PdfNumber(610),
            new PdfNumber(300), new PdfNumber(610),
            new PdfNumber(72), new PdfNumber(590),
            new PdfNumber(300), new PdfNumber(590),
        ]);
        $strikeOut->contents = new PdfString('Struck-out text');
        $writer->register($strikeOut);
        $page1->corePage()->annots[] = new PdfReference($strikeOut->objectNumber);

        // LineAnnotation with $l and $le
        $line = new LineAnnotation(
            new PdfArray([new PdfNumber(72), new PdfNumber(530), new PdfNumber(300), new PdfNumber(570)])
        );
        $line->l = new PdfArray([
            new PdfNumber(72), new PdfNumber(550),
            new PdfNumber(300), new PdfNumber(550),
        ]);
        $line->le = new PdfArray([new PdfName('OpenArrow'), new PdfName('ClosedArrow')]);
        $line->contents = new PdfString('Line annotation');
        $writer->register($line);
        $page1->corePage()->annots[] = new PdfReference($line->objectNumber);

        // SquareAnnotation with $ic
        $square = new SquareAnnotation(
            new PdfArray([new PdfNumber(72), new PdfNumber(460), new PdfNumber(200), new PdfNumber(510)])
        );
        $square->ic = new PdfArray([new PdfNumber(0.9), new PdfNumber(0.9), new PdfNumber(0.5)]);
        $square->contents = new PdfString('Square annotation');
        $writer->register($square);
        $page1->corePage()->annots[] = new PdfReference($square->objectNumber);

        // CircleAnnotation with $ic
        $circle = new CircleAnnotation(
            new PdfArray([new PdfNumber(72), new PdfNumber(380), new PdfNumber(200), new PdfNumber(440)])
        );
        $circle->ic = new PdfArray([new PdfNumber(0.5), new PdfNumber(0.8), new PdfNumber(1.0)]);
        $circle->contents = new PdfString('Circle annotation');
        $writer->register($circle);
        $page1->corePage()->annots[] = new PdfReference($circle->objectNumber);

        // PolygonAnnotation with $vertices (triangle)
        $polygon = new PolygonAnnotation(
            new PdfArray([new PdfNumber(72), new PdfNumber(280), new PdfNumber(250), new PdfNumber(360)])
        );
        $polygon->vertices = new PdfArray([
            new PdfNumber(160), new PdfNumber(360),
            new PdfNumber(72), new PdfNumber(280),
            new PdfNumber(250), new PdfNumber(280),
        ]);
        $polygon->contents = new PdfString('Polygon annotation');
        $writer->register($polygon);
        $page1->corePage()->annots[] = new PdfReference($polygon->objectNumber);

        // PolyLineAnnotation with $vertices (zigzag)
        $polyLine = new PolyLineAnnotation(
            new PdfArray([new PdfNumber(72), new PdfNumber(200), new PdfNumber(350), new PdfNumber(260)])
        );
        $polyLine->vertices = new PdfArray([
            new PdfNumber(72), new PdfNumber(230),
            new PdfNumber(140), new PdfNumber(260),
            new PdfNumber(210), new PdfNumber(200),
            new PdfNumber(280), new PdfNumber(260),
            new PdfNumber(350), new PdfNumber(230),
        ]);
        $polyLine->contents = new PdfString('PolyLine annotation');
        $writer->register($polyLine);
        $page1->corePage()->annots[] = new PdfReference($polyLine->objectNumber);

        // ====================================================================
        // Page 2 — Specialized annotations
        // ====================================================================
        $page2 = $writer->addPage(612, 792);
        $cs2 = $writer->addContentStream($page2);
        $cs2->beginText()
            ->setFont($fontName, 16)
            ->moveTextPosition(72, 750)
            ->showText('Annotation Subtypes - Page 2: Specialized')
            ->endText();

        // CaretAnnotation
        $caret = new CaretAnnotation(
            new PdfArray([new PdfNumber(72), new PdfNumber(680), new PdfNumber(120), new PdfNumber(720)])
        );
        $caret->sy = new PdfName('P');
        $caret->contents = new PdfString('Caret annotation');
        $writer->register($caret);
        $page2->corePage()->annots[] = new PdfReference($caret->objectNumber);

        // FileAttachmentAnnotation
        $fileAttach = new FileAttachmentAnnotation(
            new PdfArray([new PdfNumber(72), new PdfNumber(620), new PdfNumber(120), new PdfNumber(660)])
        );
        $fileAttach->name = new PdfName('Paperclip');
        $fileAttach->contents = new PdfString('File attachment');
        $writer->register($fileAttach);
        $page2->corePage()->annots[] = new PdfReference($fileAttach->objectNumber);

        // SoundAnnotation
        $sound = new SoundAnnotation(
            new PdfArray([new PdfNumber(72), new PdfNumber(560), new PdfNumber(120), new PdfNumber(600)])
        );
        $sound->name = new PdfName('Speaker');
        $sound->contents = new PdfString('Sound annotation');
        $writer->register($sound);
        $page2->corePage()->annots[] = new PdfReference($sound->objectNumber);

        // WatermarkAnnotation
        $watermark = new WatermarkAnnotation(
            new PdfArray([new PdfNumber(72), new PdfNumber(480), new PdfNumber(300), new PdfNumber(520)])
        );
        $watermark->contents = new PdfString('Watermark annotation');
        $writer->register($watermark);
        $page2->corePage()->annots[] = new PdfReference($watermark->objectNumber);

        // PrinterMarkAnnotation
        $printerMark = new PrinterMarkAnnotation(
            new PdfArray([new PdfNumber(72), new PdfNumber(400), new PdfNumber(120), new PdfNumber(440)])
        );
        $printerMark->mn = new PdfName('ColorBar');
        $printerMark->contents = new PdfString('Printer mark');
        $writer->register($printerMark);
        $page2->corePage()->annots[] = new PdfReference($printerMark->objectNumber);

        // ScreenAnnotation
        $screen = new ScreenAnnotation(
            new PdfArray([new PdfNumber(72), new PdfNumber(340), new PdfNumber(200), new PdfNumber(380)])
        );
        $screen->t = new PdfString('Video');
        $screen->contents = new PdfString('Screen annotation');
        $writer->register($screen);
        $page2->corePage()->annots[] = new PdfReference($screen->objectNumber);

        // MovieAnnotation
        $movie = new MovieAnnotation(
            new PdfArray([new PdfNumber(72), new PdfNumber(280), new PdfNumber(200), new PdfNumber(320)])
        );
        $movie->t = new PdfString('Clip');
        $movie->contents = new PdfString('Movie annotation');
        $writer->register($movie);
        $page2->corePage()->annots[] = new PdfReference($movie->objectNumber);

        // RedactAnnotation
        $redact = new RedactAnnotation(
            new PdfArray([new PdfNumber(72), new PdfNumber(220), new PdfNumber(300), new PdfNumber(260)])
        );
        $redact->quadPoints = new PdfArray([
            new PdfNumber(72), new PdfNumber(260),
            new PdfNumber(300), new PdfNumber(260),
            new PdfNumber(72), new PdfNumber(220),
            new PdfNumber(300), new PdfNumber(220),
        ]);
        $redact->overlayText = new PdfString('REDACTED');
        $redact->contents = new PdfString('Redact annotation');
        $writer->register($redact);
        $page2->corePage()->annots[] = new PdfReference($redact->objectNumber);

        // ====================================================================
        // Page 3 — Exotic annotations
        // ====================================================================
        $page3 = $writer->addPage(612, 792);
        $cs3 = $writer->addContentStream($page3);
        $cs3->beginText()
            ->setFont($fontName, 16)
            ->moveTextPosition(72, 750)
            ->showText('Annotation Subtypes - Page 3: Exotic')
            ->endText();

        // ThreeDAnnotation
        $threeD = new ThreeDAnnotation(
            new PdfArray([new PdfNumber(72), new PdfNumber(680), new PdfNumber(300), new PdfNumber(720)])
        );
        $threeD->contents = new PdfString('3D annotation');
        $writer->register($threeD);
        $page3->corePage()->annots[] = new PdfReference($threeD->objectNumber);

        // ProjectionAnnotation
        $projection = new ProjectionAnnotation(
            new PdfArray([new PdfNumber(72), new PdfNumber(580), new PdfNumber(300), new PdfNumber(620)])
        );
        $projection->contents = new PdfString('Projection annotation');
        $writer->register($projection);
        $page3->corePage()->annots[] = new PdfReference($projection->objectNumber);

        // RichMediaAnnotation
        $richMedia = new RichMediaAnnotation(
            new PdfArray([new PdfNumber(72), new PdfNumber(480), new PdfNumber(300), new PdfNumber(520)])
        );
        $richMedia->contents = new PdfString('RichMedia annotation');
        $writer->register($richMedia);
        $page3->corePage()->annots[] = new PdfReference($richMedia->objectNumber);

        // TrapNetAnnotation
        $trapNet = new TrapNetAnnotation(
            new PdfArray([new PdfNumber(72), new PdfNumber(380), new PdfNumber(300), new PdfNumber(420)])
        );
        $trapNet->contents = new PdfString('TrapNet annotation');
        $writer->register($trapNet);
        $page3->corePage()->annots[] = new PdfReference($trapNet->objectNumber);

        // ====================================================================
        // Save and validate
        // ====================================================================
        $writer->save(self::OUTPUT_FILE);

        self::assertFileExists(self::OUTPUT_FILE);

        $content = file_get_contents(self::OUTPUT_FILE);
        self::assertNotFalse($content);
        self::assertStringStartsWith('%PDF-', $content);

        // Verify all annotation subtypes are present
        self::assertStringContainsString('/Subtype /Underline', $content);
        self::assertStringContainsString('/Subtype /Squiggly', $content);
        self::assertStringContainsString('/Subtype /StrikeOut', $content);
        self::assertStringContainsString('/Subtype /Line', $content);
        self::assertStringContainsString('/Subtype /Square', $content);
        self::assertStringContainsString('/Subtype /Circle', $content);
        self::assertStringContainsString('/Subtype /Polygon', $content);
        self::assertStringContainsString('/Subtype /PolyLine', $content);
        self::assertStringContainsString('/Subtype /Caret', $content);
        self::assertStringContainsString('/Subtype /FileAttachment', $content);
        self::assertStringContainsString('/Subtype /Sound', $content);
        self::assertStringContainsString('/Subtype /Watermark', $content);
        self::assertStringContainsString('/Subtype /PrinterMark', $content);
        self::assertStringContainsString('/Subtype /Screen', $content);
        self::assertStringContainsString('/Subtype /Movie', $content);
        self::assertStringContainsString('/Subtype /Redact', $content);
        self::assertStringContainsString('/Subtype /3D', $content);
        self::assertStringContainsString('/Subtype /Projection', $content);
        self::assertStringContainsString('/Subtype /RichMedia', $content);
        self::assertStringContainsString('/Subtype /TrapNet', $content);
        self::assertStringContainsString('%%EOF', $content);
    }
}
