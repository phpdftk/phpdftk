<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Writer\Tests;

use Phpdftk\Geometry\Rectangle;
use Phpdftk\Pdf\Core\Document\ViewerPreferences;
use Phpdftk\Pdf\Writer\Action;
use Phpdftk\Pdf\Writer\Pdf;
use Phpdftk\Pdf\Writer\PdfDoc;
use Phpdftk\Tests\Support\QpdfValidationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group("qpdf")]
class Phase4WrappingTest extends TestCase
{
    use QpdfValidationTrait;

    // -----------------------------------------------------------------------
    // 4.7 — Page rotation + boxes
    // -----------------------------------------------------------------------

    public function testSetRotationStoresAngleOnPage(): void
    {
        $doc = new PdfDoc(compressStreams: false);
        $page = $doc->addPage();
        $page->setRotation(90);
        $bytes = $doc->writer()->generate();
        self::assertMatchesRegularExpression('/\/Rotate 90\b/', $bytes);
    }

    public function testSetRotationNormalisesNegativeAngle(): void
    {
        $doc = new PdfDoc();
        $page = $doc->addPage();
        $page->setRotation(-90);
        self::assertSame(270, $page->corePage()->rotate);
    }

    public function testSetRotationRejectsNonMultipleOf90(): void
    {
        $doc = new PdfDoc();
        $page = $doc->addPage();
        $this->expectException(\InvalidArgumentException::class);
        $page->setRotation(45);
    }

    public function testSetPageBoxesPopulatesDictEntries(): void
    {
        $doc = new PdfDoc(compressStreams: false);
        $page = $doc->addPage();
        $page->setCropBox(new Rectangle(10, 10, 580, 770));
        $page->setBleedBox(new Rectangle(5, 5, 590, 780));
        $page->setTrimBox(new Rectangle(15, 15, 570, 760));
        $page->setArtBox(new Rectangle(20, 20, 560, 750));
        $bytes = $doc->writer()->generate();
        self::assertStringContainsString('/CropBox', $bytes);
        self::assertStringContainsString('/BleedBox', $bytes);
        self::assertStringContainsString('/TrimBox', $bytes);
        self::assertStringContainsString('/ArtBox', $bytes);
    }

    // -----------------------------------------------------------------------
    // 4.10 — Viewer preferences
    // -----------------------------------------------------------------------

    public function testSetViewerPreferencesClosureMutatesFreshInstance(): void
    {
        $doc = new PdfDoc(compressStreams: false);
        $doc->addPage();
        $doc->setViewerPreferences(function (ViewerPreferences $vp): void {
            $vp->displayDocTitle = true;
            $vp->fitWindow = true;
        });
        $bytes = $doc->writer()->generate();
        self::assertStringContainsString('/ViewerPreferences', $bytes);
        self::assertStringContainsString('/DisplayDocTitle true', $bytes);
        self::assertStringContainsString('/FitWindow true', $bytes);
    }

    public function testSetViewerPreferencesAcceptsPreBuiltInstance(): void
    {
        $vp = new ViewerPreferences();
        $vp->centerWindow = true;
        $doc = new PdfDoc(compressStreams: false);
        $doc->addPage();
        $doc->setViewerPreferences($vp);
        $bytes = $doc->writer()->generate();
        self::assertStringContainsString('/CenterWindow true', $bytes);
    }

    public function testPdfForwarderForViewerPreferences(): void
    {
        $pdf = new Pdf(compressStreams: false);
        $pdf->setViewerPreferences(function (ViewerPreferences $vp): void {
            $vp->displayDocTitle = true;
        });
        $bytes = $pdf->toBytes();
        self::assertStringContainsString('/DisplayDocTitle true', $bytes);
    }

    // -----------------------------------------------------------------------
    // 4.4 — Graphics state transforms + opacity
    // -----------------------------------------------------------------------

    public function testRotateEmitsConcatMatrix(): void
    {
        $doc = new PdfDoc(compressStreams: false);
        $page = $doc->addPage();
        $page->rotate(45);
        $bytes = $doc->writer()->generate();
        // cm operator: a b c d e f cm — at 45°, cos = sin ≈ 0.707107
        self::assertMatchesRegularExpression('/0\.707.+0\.707.+ cm/', $bytes);
    }

    public function testScaleAndTranslateEmitMatrices(): void
    {
        $doc = new PdfDoc(compressStreams: false);
        $page = $doc->addPage();
        $page->scale(2.0, 0.5);
        $page->translate(50.0, 100.0);
        $bytes = $doc->writer()->generate();
        self::assertStringContainsString('2 0 0 0.5 0 0 cm', $bytes);
        self::assertStringContainsString('1 0 0 1 50 100 cm', $bytes);
    }

    public function testWithTransformWrapsClosureInQ_Q(): void
    {
        $doc = new PdfDoc(compressStreams: false);
        $page = $doc->addPage();
        $page->withTransform(function ($p): void {
            $p->rotate(30);
        });
        $bytes = $doc->writer()->generate();
        // q ... Q delimits the transform scope
        self::assertMatchesRegularExpression('/\nq\n.*cm.*\nQ\n/s', $bytes);
    }

    public function testSetOpacityRegistersExtGStateAndEmitsGs(): void
    {
        $doc = new PdfDoc(compressStreams: false);
        $page = $doc->addPage();
        $page->setOpacity(0.5);
        $bytes = $doc->writer()->generate();
        self::assertStringContainsString('/Type /ExtGState', $bytes);
        self::assertStringContainsString('/CA 0.5', $bytes);
        self::assertStringContainsString('/ca 0.5', $bytes);
        self::assertMatchesRegularExpression('/\/GS_op_0\.500_0\.500 gs/', $bytes);
    }

    public function testSetOpacityClampsOutOfRangeValues(): void
    {
        $doc = new PdfDoc(compressStreams: false);
        $page = $doc->addPage();
        $page->setOpacity(2.5, -1.0);
        $bytes = $doc->writer()->generate();
        // 2.5 clamps to 1.0, -1.0 clamps to 0.0
        self::assertStringContainsString('/CA 1', $bytes);
        self::assertStringContainsString('/ca 0', $bytes);
    }

    public function testSetOpacityReusesExtGStateAcrossCalls(): void
    {
        $doc = new PdfDoc(compressStreams: false);
        $page = $doc->addPage();
        $page->setOpacity(0.5);
        $page->setOpacity(0.5); // same key — should reuse
        $bytes = $doc->writer()->generate();
        // Two gs operators (one per call), but only one ExtGState registered.
        self::assertSame(2, substr_count($bytes, '/GS_op_0.500_0.500 gs'));
        self::assertSame(1, substr_count($bytes, '/Type /ExtGState'));
    }

    // -----------------------------------------------------------------------
    // 4.3 — File attachments
    // -----------------------------------------------------------------------

    public function testAttachFileFromDiskCreatesFileSpecAndEmbeddedFile(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'phpdftk_attach_');
        file_put_contents($tmp, 'hello world');
        try {
            $doc = new PdfDoc(compressStreams: false);
            $doc->addPage();
            $fileSpec = $doc->attachFile($tmp, description: 'A greeting', mimeType: 'text/plain');
            $bytes = $doc->writer()->generate();
            self::assertStringContainsString('/Type /Filespec', $bytes);
            self::assertStringContainsString('/Type /EmbeddedFile', $bytes);
            self::assertStringContainsString('hello world', $bytes);
            self::assertStringContainsString('A greeting', $bytes);
            self::assertNotEmpty($fileSpec->ef);
        } finally {
            unlink($tmp);
        }
    }

    public function testAttachFileBytesInMemoryUsesProvidedName(): void
    {
        $doc = new PdfDoc(compressStreams: false);
        $doc->addPage();
        $doc->attachFileBytes('invoice.xml', '<invoice/>', relationship: 'Alternative');
        $bytes = $doc->writer()->generate();
        self::assertStringContainsString('invoice.xml', $bytes);
        self::assertStringContainsString('/AFRelationship /Alternative', $bytes);
    }

    public function testMultipleAttachmentsAccumulateInAfArray(): void
    {
        $doc = new PdfDoc(compressStreams: false);
        $doc->addPage();
        $doc->attachFileBytes('a.txt', 'A');
        $doc->attachFileBytes('b.txt', 'B');
        $doc->attachFileBytes('c.txt', 'C');
        $bytes = $doc->writer()->generate();
        // Catalog /AF array references all three FileSpec objects.
        self::assertMatchesRegularExpression('/\/AF \[ \d+ 0 R \d+ 0 R \d+ 0 R \]/', $bytes);
    }

    public function testPdfForwarderForAttachFile(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'phpdftk_attach_');
        file_put_contents($tmp, 'pdf data');
        try {
            $pdf = new Pdf(compressStreams: false);
            $pdf->addPage();
            $pdf->attachFile($tmp);
            $bytes = $pdf->toBytes();
            self::assertStringContainsString('/Type /Filespec', $bytes);
            self::assertStringContainsString('pdf data', $bytes);
        } finally {
            unlink($tmp);
        }
    }

    // -----------------------------------------------------------------------
    // 4.6 — Layers (Optional Content)
    // -----------------------------------------------------------------------

    public function testAddLayerCreatesOCGAndOCProperties(): void
    {
        $doc = new PdfDoc(compressStreams: false);
        $doc->addPage();
        $doc->addLayer('Annotations');
        $bytes = $doc->writer()->generate();
        self::assertStringContainsString('/Type /OCG', $bytes);
        // OCG name is a PdfName, not a string literal — appears as /Annotations.
        self::assertStringContainsString('/Name /Annotations', $bytes);
        self::assertStringContainsString('/OCProperties', $bytes);
    }

    public function testAddLayerVisibilityRoutesToOnAndOffLists(): void
    {
        $doc = new PdfDoc(compressStreams: false);
        $doc->addPage();
        $doc->addLayer('Visible', visible: true);
        $doc->addLayer('Hidden', visible: false);
        $bytes = $doc->writer()->generate();
        // Default config carries both ON and OFF arrays with one ref each.
        self::assertMatchesRegularExpression('/\/ON \[ \d+ 0 R \]/', $bytes);
        self::assertMatchesRegularExpression('/\/OFF \[ \d+ 0 R \]/', $bytes);
    }

    public function testInLayerWrapsContentStreamWithMarkedContent(): void
    {
        $doc = new PdfDoc(compressStreams: false);
        $page = $doc->addPage();
        $layer = $doc->addLayer('Markup');
        $page->inLayer($layer, function ($p): void {
            $p->drawLine(72, 72, 200, 200);
        });
        $bytes = $doc->writer()->generate();
        self::assertMatchesRegularExpression('/\/OC \/MC\d+ BDC.*EMC/s', $bytes);
        self::assertStringContainsString('/Properties', $bytes);
    }

    // -----------------------------------------------------------------------
    // 4.8 — Action factories
    // -----------------------------------------------------------------------

    public function testActionUriBuildsUriActionWithGivenUrl(): void
    {
        $action = Action::uri('https://example.com/');
        self::assertStringContainsString('/S /URI', $action->toPdf());
        self::assertStringContainsString('https://example.com/', $action->toPdf());
    }

    public function testActionJavascriptWrapsCodeInJsAction(): void
    {
        $action = Action::javascript('app.alert("hi");');
        $serialised = $action->toPdf();
        self::assertStringContainsString('/S /JavaScript', $serialised);
        self::assertStringContainsString('app.alert', $serialised);
    }

    public function testActionNamedUsesProvidedName(): void
    {
        $action = Action::namedAction('NextPage');
        self::assertStringContainsString('/N /NextPage', $action->toPdf());
    }

    public function testActionResetFormWithoutFieldsResetsAll(): void
    {
        $action = Action::resetForm();
        $pdf = $action->toPdf();
        self::assertStringContainsString('/S /ResetForm', $pdf);
        self::assertStringNotContainsString('/Fields', $pdf);
    }

    public function testActionResetFormWithFieldsListsThem(): void
    {
        $action = Action::resetForm(['name', 'email']);
        $pdf = $action->toPdf();
        self::assertStringContainsString('/Fields', $pdf);
        self::assertStringContainsString('(name)', $pdf);
    }

    public function testSetOpenActionRegistersAndWiresToCatalog(): void
    {
        $doc = new PdfDoc(compressStreams: false);
        $doc->addPage();
        $doc->setOpenAction(Action::uri('https://phpdftk.dev/'));
        $bytes = $doc->writer()->generate();
        self::assertMatchesRegularExpression('/\/OpenAction \d+ 0 R/', $bytes);
        self::assertStringContainsString('/S /URI', $bytes);
    }

    // -----------------------------------------------------------------------
    // 4.1 — Annotation builders
    // -----------------------------------------------------------------------

    public function testAddStickyNoteEmitsTextAnnotation(): void
    {
        $doc = new PdfDoc(compressStreams: false);
        $page = $doc->addPage();
        $doc->addStickyNote($page, 72, 720, 'Quick note for the reader');
        $bytes = $doc->writer()->generate();
        self::assertStringContainsString('/Subtype /Text', $bytes);
        self::assertStringContainsString('Quick note for the reader', $bytes);
    }

    public function testAddHighlightUsesQuadPointsAndDerivedRect(): void
    {
        $doc = new PdfDoc(compressStreams: false);
        $page = $doc->addPage();
        $doc->addHighlight($page, [
            new Rectangle(72, 700, 200, 14),
            new Rectangle(72, 680, 180, 14),
        ]);
        $bytes = $doc->writer()->generate();
        self::assertStringContainsString('/Subtype /Highlight', $bytes);
        self::assertStringContainsString('/QuadPoints', $bytes);
    }

    public function testAddInkAcceptsMultipleStrokes(): void
    {
        $doc = new PdfDoc(compressStreams: false);
        $page = $doc->addPage();
        $doc->addInk($page, [
            [100.0, 100.0, 150.0, 120.0, 200.0, 110.0],
            [220.0, 200.0, 260.0, 240.0],
        ]);
        $bytes = $doc->writer()->generate();
        self::assertStringContainsString('/Subtype /Ink', $bytes);
        self::assertStringContainsString('/InkList', $bytes);
    }

    public function testAddLineAnnotationSetsLEndpointsAndRect(): void
    {
        $doc = new PdfDoc(compressStreams: false);
        $page = $doc->addPage();
        $doc->addLineAnnotation($page, 100, 200, 300, 250);
        $bytes = $doc->writer()->generate();
        self::assertStringContainsString('/Subtype /Line', $bytes);
        self::assertMatchesRegularExpression('/\/L \[ 100 200 300 250 \]/', $bytes);
    }

    public function testAddPolygonRecordsVerticesAndComputesBoundingRect(): void
    {
        $doc = new PdfDoc(compressStreams: false);
        $page = $doc->addPage();
        $doc->addPolygon($page, [
            [100.0, 100.0],
            [200.0, 100.0],
            [150.0, 200.0],
        ]);
        $bytes = $doc->writer()->generate();
        self::assertStringContainsString('/Subtype /Polygon', $bytes);
        self::assertStringContainsString('/Vertices', $bytes);
    }

    public function testAddSquareCircleStampWatermark(): void
    {
        $doc = new PdfDoc(compressStreams: false);
        $page = $doc->addPage();
        $r = new Rectangle(72, 200, 100, 100);
        $doc->addSquare($page, $r);
        $doc->addCircleAnnotation($page, $r);
        $doc->addStamp($page, $r, 'Approved');
        $doc->addWatermarkAnnotation($page, $r);
        $bytes = $doc->writer()->generate();
        self::assertStringContainsString('/Subtype /Square', $bytes);
        self::assertStringContainsString('/Subtype /Circle', $bytes);
        self::assertStringContainsString('/Subtype /Stamp', $bytes);
        self::assertStringContainsString('/Subtype /Watermark', $bytes);
        // Stamp name is a PdfName, not a string literal.
        self::assertStringContainsString('/Name /Approved', $bytes);
    }

    public function testAddHighlightRequiresAtLeastOneQuad(): void
    {
        $doc = new PdfDoc();
        $page = $doc->addPage();
        $this->expectException(\InvalidArgumentException::class);
        $doc->addHighlight($page, []);
    }

    public function testAddPolygonRequiresAtLeastOnePoint(): void
    {
        $doc = new PdfDoc();
        $page = $doc->addPage();
        $this->expectException(\InvalidArgumentException::class);
        $doc->addPolygon($page, []);
    }

    public function testAnnotationAttachesToPageAnnots(): void
    {
        $doc = new PdfDoc();
        $page = $doc->addPage();
        self::assertCount(0, $page->corePage()->annots);
        $doc->addStickyNote($page, 50, 50, 'x');
        $doc->addStickyNote($page, 60, 60, 'y');
        self::assertCount(2, $page->corePage()->annots);
    }
}
