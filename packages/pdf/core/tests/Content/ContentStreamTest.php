<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Tests\Content;

use PHPUnit\Framework\TestCase;
use ApprLabs\Color\CmykColor;
use ApprLabs\Color\GrayColor;
use ApprLabs\Color\RgbColor;
use ApprLabs\Pdf\Core\Content\ContentStream;
use ApprLabs\Pdf\Core\Content\Resources;
use ApprLabs\Geometry\Matrix;
use ApprLabs\Geometry\Rectangle;
use ApprLabs\Pdf\Core\PdfReference;

class ContentStreamTest extends TestCase
{
    // -----------------------------------------------------------------------
    // ContentStream — text operators
    // -----------------------------------------------------------------------

    public function testBeginEndText(): void
    {
        $cs = new ContentStream();
        $cs->objectNumber = 1;
        $pdf = $cs->beginText()->endText()->toPdf();
        self::assertStringContainsString('BT', $pdf);
        self::assertStringContainsString('ET', $pdf);
    }

    public function testSetFont(): void
    {
        $cs = new ContentStream();
        $cs->objectNumber = 1;
        $pdf = $cs->setFont('F1', 12)->toPdf();
        self::assertStringContainsString('/F1 12 Tf', $pdf);
    }

    public function testShowText(): void
    {
        $cs = new ContentStream();
        $cs->objectNumber = 1;
        $pdf = $cs->showText('Hello PDF')->toPdf();
        self::assertStringContainsString('(Hello PDF) Tj', $pdf);
    }

    public function testShowTextEscapesParens(): void
    {
        $cs = new ContentStream();
        $cs->objectNumber = 1;
        $pdf = $cs->showText('Hello (World)')->toPdf();
        self::assertStringContainsString('\\(World\\)', $pdf);
    }

    public function testShowTextEscapesBackslash(): void
    {
        $cs = new ContentStream();
        $cs->objectNumber = 1;
        $pdf = $cs->showText('back\\slash')->toPdf();
        self::assertStringContainsString('back\\\\slash', $pdf);
    }

    public function testMoveTextPosition(): void
    {
        $cs = new ContentStream();
        $cs->objectNumber = 1;
        $pdf = $cs->moveTextPosition(72, 720)->toPdf();
        self::assertStringContainsString('72 720 Td', $pdf);
    }

    public function testMoveTextPositionNewLine(): void
    {
        $cs = new ContentStream();
        $cs->objectNumber = 1;
        $pdf = $cs->moveTextPositionNewLine(0, -14)->toPdf();
        self::assertStringContainsString('TD', $pdf);
    }

    public function testNextLine(): void
    {
        $cs = new ContentStream();
        $cs->objectNumber = 1;
        $pdf = $cs->nextLine()->toPdf();
        self::assertStringContainsString('T*', $pdf);
    }

    public function testSetTextMatrix(): void
    {
        $cs = new ContentStream();
        $cs->objectNumber = 1;
        $pdf = $cs->setTextMatrix(1, 0, 0, 1, 72, 720)->toPdf();
        self::assertStringContainsString('Tm', $pdf);
    }

    public function testSetCharSpacing(): void
    {
        $cs = new ContentStream();
        $cs->objectNumber = 1;
        $pdf = $cs->setCharSpacing(1.5)->toPdf();
        self::assertStringContainsString('1.5 Tc', $pdf);
    }

    public function testSetWordSpacing(): void
    {
        $cs = new ContentStream();
        $cs->objectNumber = 1;
        $pdf = $cs->setWordSpacing(2.0)->toPdf();
        self::assertStringContainsString('2 Tw', $pdf);
    }

    public function testSetHorizontalScaling(): void
    {
        $cs = new ContentStream();
        $cs->objectNumber = 1;
        $pdf = $cs->setHorizontalScaling(100)->toPdf();
        self::assertStringContainsString('Tz', $pdf);
    }

    public function testSetTextLeading(): void
    {
        $cs = new ContentStream();
        $cs->objectNumber = 1;
        $pdf = $cs->setTextLeading(14)->toPdf();
        self::assertStringContainsString('14 TL', $pdf);
    }

    public function testSetTextRenderingMode(): void
    {
        $cs = new ContentStream();
        $cs->objectNumber = 1;
        $pdf = $cs->setTextRenderingMode(0)->toPdf();
        self::assertStringContainsString('0 Tr', $pdf);
    }

    public function testSetTextRise(): void
    {
        $cs = new ContentStream();
        $cs->objectNumber = 1;
        $pdf = $cs->setTextRise(5)->toPdf();
        self::assertStringContainsString('5 Ts', $pdf);
    }

    public function testShowTextArray(): void
    {
        $cs = new ContentStream();
        $cs->objectNumber = 1;
        $pdf = $cs->showTextArray(['Hello', -30, 'World'])->toPdf();
        self::assertStringContainsString('TJ', $pdf);
        self::assertStringContainsString('(Hello)', $pdf);
        self::assertStringContainsString('-30', $pdf);
    }

    // -----------------------------------------------------------------------
    // ContentStream — graphics state operators
    // -----------------------------------------------------------------------

    public function testSaveRestoreGraphicsState(): void
    {
        $cs = new ContentStream();
        $cs->objectNumber = 1;
        $pdf = $cs->saveGraphicsState()->restoreGraphicsState()->toPdf();
        self::assertStringContainsString('q', $pdf);
        self::assertStringContainsString('Q', $pdf);
    }

    public function testSetLineWidth(): void
    {
        $cs = new ContentStream();
        $cs->objectNumber = 1;
        $pdf = $cs->setLineWidth(2.5)->toPdf();
        self::assertStringContainsString('2.5 w', $pdf);
    }

    public function testSetLineCap(): void
    {
        $cs = new ContentStream();
        $cs->objectNumber = 1;
        $pdf = $cs->setLineCap(1)->toPdf();
        self::assertStringContainsString('1 J', $pdf);
    }

    public function testSetLineJoin(): void
    {
        $cs = new ContentStream();
        $cs->objectNumber = 1;
        $pdf = $cs->setLineJoin(2)->toPdf();
        self::assertStringContainsString('2 j', $pdf);
    }

    public function testSetMiterLimit(): void
    {
        $cs = new ContentStream();
        $cs->objectNumber = 1;
        $pdf = $cs->setMiterLimit(10)->toPdf();
        self::assertStringContainsString('10 M', $pdf);
    }

    public function testSetDashPattern(): void
    {
        $cs = new ContentStream();
        $cs->objectNumber = 1;
        $pdf = $cs->setDashPattern([3, 2], 0)->toPdf();
        self::assertStringContainsString('d', $pdf);
        self::assertStringContainsString('[ 3 2 ]', $pdf);
    }

    public function testSetRenderingIntent(): void
    {
        $cs = new ContentStream();
        $cs->objectNumber = 1;
        $pdf = $cs->setRenderingIntent('RelativeColorimetric')->toPdf();
        self::assertStringContainsString('/RelativeColorimetric ri', $pdf);
    }

    public function testSetFlatness(): void
    {
        $cs = new ContentStream();
        $cs->objectNumber = 1;
        $pdf = $cs->setFlatness(1.0)->toPdf();
        self::assertStringContainsString('1 i', $pdf);
    }

    public function testSetGraphicsState(): void
    {
        $cs = new ContentStream();
        $cs->objectNumber = 1;
        $pdf = $cs->setGraphicsState('GS1')->toPdf();
        self::assertStringContainsString('/GS1 gs', $pdf);
    }

    public function testConcatMatrix(): void
    {
        $cs = new ContentStream();
        $cs->objectNumber = 1;
        $pdf = $cs->concatMatrix(1, 0, 0, 1, 100, 200)->toPdf();
        self::assertStringContainsString('cm', $pdf);
    }

    public function testConcatMatrixObject(): void
    {
        $cs = new ContentStream();
        $cs->objectNumber = 1;
        $m = Matrix::identity()->translate(50, 100);
        $pdf = $cs->concatMatrixObject($m)->toPdf();
        self::assertStringContainsString('cm', $pdf);
    }

    // -----------------------------------------------------------------------
    // ContentStream — path operators
    // -----------------------------------------------------------------------

    public function testMoveTo(): void
    {
        $cs = new ContentStream();
        $cs->objectNumber = 1;
        $pdf = $cs->moveTo(100, 200)->toPdf();
        self::assertStringContainsString('100 200 m', $pdf);
    }

    public function testLineTo(): void
    {
        $cs = new ContentStream();
        $cs->objectNumber = 1;
        $pdf = $cs->lineTo(200, 300)->toPdf();
        self::assertStringContainsString('200 300 l', $pdf);
    }

    public function testCurveTo(): void
    {
        $cs = new ContentStream();
        $cs->objectNumber = 1;
        $pdf = $cs->curveTo(10, 20, 30, 40, 50, 60)->toPdf();
        self::assertStringContainsString('c', $pdf);
    }

    public function testCurveToV(): void
    {
        $cs = new ContentStream();
        $cs->objectNumber = 1;
        $pdf = $cs->curveToV(10, 20, 30, 40)->toPdf();
        self::assertStringContainsString('v', $pdf);
    }

    public function testCurveToY(): void
    {
        $cs = new ContentStream();
        $cs->objectNumber = 1;
        $pdf = $cs->curveToY(10, 20, 30, 40)->toPdf();
        self::assertStringContainsString('y', $pdf);
    }

    public function testClosePath(): void
    {
        $cs = new ContentStream();
        $cs->objectNumber = 1;
        $pdf = $cs->closePath()->toPdf();
        self::assertStringContainsString('h', $pdf);
    }

    public function testRectangle(): void
    {
        $cs = new ContentStream();
        $cs->objectNumber = 1;
        $pdf = $cs->rectangle(10, 20, 100, 50)->toPdf();
        self::assertStringContainsString('10 20 100 50 re', $pdf);
    }

    public function testRectangleObject(): void
    {
        $cs = new ContentStream();
        $cs->objectNumber = 1;
        $rect = new Rectangle(10, 20, 100, 50);
        $pdf = $cs->rectangleObject($rect)->toPdf();
        self::assertStringContainsString('re', $pdf);
    }

    // -----------------------------------------------------------------------
    // ContentStream — painting operators
    // -----------------------------------------------------------------------

    public function testStroke(): void
    {
        $cs = new ContentStream();
        $cs->objectNumber = 1;
        $pdf = $cs->stroke()->toPdf();
        // 'S' operator appears on its own line
        self::assertMatchesRegularExpression('/\nS(\n|$)/', $pdf);
    }

    public function testCloseAndStroke(): void
    {
        $cs = new ContentStream();
        $cs->objectNumber = 1;
        $pdf = $cs->closeAndStroke()->toPdf();
        // 's' operator appears on its own line
        self::assertMatchesRegularExpression('/\ns(\n|$)/', $pdf);
    }

    public function testFill(): void
    {
        $cs = new ContentStream();
        $cs->objectNumber = 1;
        $pdf = $cs->fill()->toPdf();
        // 'f' operator appears on its own line
        self::assertMatchesRegularExpression('/\nf(\n|$)/', $pdf);
    }

    public function testFillEvenOdd(): void
    {
        $cs = new ContentStream();
        $cs->objectNumber = 1;
        $pdf = $cs->fillEvenOdd()->toPdf();
        self::assertStringContainsString('f*', $pdf);
    }

    public function testFillAndStroke(): void
    {
        $cs = new ContentStream();
        $cs->objectNumber = 1;
        $pdf = $cs->fillAndStroke()->toPdf();
        self::assertMatchesRegularExpression('/\nB(\n|$)/', $pdf);
    }

    public function testFillAndStrokeEvenOdd(): void
    {
        $cs = new ContentStream();
        $cs->objectNumber = 1;
        $pdf = $cs->fillAndStrokeEvenOdd()->toPdf();
        self::assertStringContainsString('B*', $pdf);
    }

    public function testCloseFillAndStroke(): void
    {
        $cs = new ContentStream();
        $cs->objectNumber = 1;
        $pdf = $cs->closeFillAndStroke()->toPdf();
        self::assertMatchesRegularExpression('/\nb(\n|$)/', $pdf);
    }

    public function testCloseFillAndStrokeEvenOdd(): void
    {
        $cs = new ContentStream();
        $cs->objectNumber = 1;
        $pdf = $cs->closeFillAndStrokeEvenOdd()->toPdf();
        self::assertStringContainsString('b*', $pdf);
    }

    public function testEndPath(): void
    {
        $cs = new ContentStream();
        $cs->objectNumber = 1;
        $pdf = $cs->endPath()->toPdf();
        self::assertMatchesRegularExpression('/\nn(\n|$)/', $pdf);
    }

    public function testClip(): void
    {
        $cs = new ContentStream();
        $cs->objectNumber = 1;
        $pdf = $cs->clip()->toPdf();
        self::assertMatchesRegularExpression('/\nW(\n|$)/', $pdf);
    }

    public function testClipEvenOdd(): void
    {
        $cs = new ContentStream();
        $cs->objectNumber = 1;
        $pdf = $cs->clipEvenOdd()->toPdf();
        self::assertStringContainsString('W*', $pdf);
    }

    // -----------------------------------------------------------------------
    // ContentStream — color operators
    // -----------------------------------------------------------------------

    public function testSetStrokeColorRGB(): void
    {
        $cs = new ContentStream();
        $cs->objectNumber = 1;
        $pdf = $cs->setStrokeColorRGB(1.0, 0.0, 0.0)->toPdf();
        self::assertStringContainsString('1 0 0 RG', $pdf);
    }

    public function testSetFillColorRGB(): void
    {
        $cs = new ContentStream();
        $cs->objectNumber = 1;
        $pdf = $cs->setFillColorRGB(0.0, 1.0, 0.0)->toPdf();
        self::assertStringContainsString('0 1 0 rg', $pdf);
    }

    public function testSetStrokeColorCMYK(): void
    {
        $cs = new ContentStream();
        $cs->objectNumber = 1;
        $pdf = $cs->setStrokeColorCMYK(0, 1, 1, 0)->toPdf();
        self::assertStringContainsString('K', $pdf);
    }

    public function testSetFillColorCMYK(): void
    {
        $cs = new ContentStream();
        $cs->objectNumber = 1;
        $pdf = $cs->setFillColorCMYK(0, 0, 0, 1)->toPdf();
        self::assertStringContainsString(' k', $pdf);
    }

    public function testSetStrokeColorGray(): void
    {
        $cs = new ContentStream();
        $cs->objectNumber = 1;
        $pdf = $cs->setStrokeColorGray(0.5)->toPdf();
        self::assertStringContainsString('0.5 G', $pdf);
    }

    public function testSetFillColorGray(): void
    {
        $cs = new ContentStream();
        $cs->objectNumber = 1;
        $pdf = $cs->setFillColorGray(0.0)->toPdf();
        self::assertStringContainsString('0 g', $pdf);
    }

    public function testSetStrokeColorSpace(): void
    {
        $cs = new ContentStream();
        $cs->objectNumber = 1;
        $pdf = $cs->setStrokeColorSpace('DeviceCMYK')->toPdf();
        self::assertStringContainsString('/DeviceCMYK CS', $pdf);
    }

    public function testSetFillColorSpace(): void
    {
        $cs = new ContentStream();
        $cs->objectNumber = 1;
        $pdf = $cs->setFillColorSpace('DeviceGray')->toPdf();
        self::assertStringContainsString('/DeviceGray cs', $pdf);
    }

    public function testSetStrokeColor(): void
    {
        $cs = new ContentStream();
        $cs->objectNumber = 1;
        $pdf = $cs->setStrokeColor(1.0, 0.0, 0.0)->toPdf();
        self::assertStringContainsString('SCN', $pdf);
    }

    public function testSetFillColor(): void
    {
        $cs = new ContentStream();
        $cs->objectNumber = 1;
        $pdf = $cs->setFillColor(0.0, 0.0, 1.0)->toPdf();
        self::assertStringContainsString('scn', $pdf);
    }

    // -----------------------------------------------------------------------
    // ContentStream — typed color methods (using phpdftk/color objects)
    // -----------------------------------------------------------------------

    public function testSetFillRgbColor(): void
    {
        $cs = new ContentStream();
        $cs->objectNumber = 1;
        $color = new RgbColor(1.0, 0.0, 0.0);
        $pdf = $cs->setFillRgbColor($color)->toPdf();
        self::assertStringContainsString('1 0 0 rg', $pdf);
    }

    public function testSetStrokeRgbColor(): void
    {
        $cs = new ContentStream();
        $cs->objectNumber = 1;
        $color = new RgbColor(0.0, 0.0, 1.0);
        $pdf = $cs->setStrokeRgbColor($color)->toPdf();
        self::assertStringContainsString('0 0 1 RG', $pdf);
    }

    public function testSetFillCmykColor(): void
    {
        $cs = new ContentStream();
        $cs->objectNumber = 1;
        $color = new CmykColor(0.0, 0.0, 0.0, 1.0);
        $pdf = $cs->setFillCmykColor($color)->toPdf();
        self::assertStringContainsString('k', $pdf);
    }

    public function testSetStrokeCmykColor(): void
    {
        $cs = new ContentStream();
        $cs->objectNumber = 1;
        $color = new CmykColor(1.0, 0.0, 0.0, 0.0);
        $pdf = $cs->setStrokeCmykColor($color)->toPdf();
        self::assertStringContainsString('K', $pdf);
    }

    public function testSetFillGrayColor(): void
    {
        $cs = new ContentStream();
        $cs->objectNumber = 1;
        $color = GrayColor::black();
        $pdf = $cs->setFillGrayColor($color)->toPdf();
        self::assertStringContainsString('0 g', $pdf);
    }

    public function testSetStrokeGrayColor(): void
    {
        $cs = new ContentStream();
        $cs->objectNumber = 1;
        $color = GrayColor::white();
        $pdf = $cs->setStrokeGrayColor($color)->toPdf();
        self::assertStringContainsString('1 G', $pdf);
    }

    // -----------------------------------------------------------------------
    // ContentStream — XObject and raw
    // -----------------------------------------------------------------------

    public function testDoXObject(): void
    {
        $cs = new ContentStream();
        $cs->objectNumber = 1;
        $pdf = $cs->doXObject('Im1')->toPdf();
        self::assertStringContainsString('/Im1 Do', $pdf);
    }

    public function testRawOperator(): void
    {
        $cs = new ContentStream();
        $cs->objectNumber = 1;
        $pdf = $cs->raw('1 0 0 1 0 0 cm')->toPdf();
        self::assertStringContainsString('1 0 0 1 0 0 cm', $pdf);
    }

    public function testInlineImage(): void
    {
        $cs = new ContentStream();
        $cs->objectNumber = 1;
        $pdf = $cs->inlineImage(['W' => '10', 'H' => '10', 'CS' => '/G', 'BPC' => '8'], "\x00")->toPdf();
        self::assertStringContainsString('BI', $pdf);
        self::assertStringContainsString('ID', $pdf);
        self::assertStringContainsString('EI', $pdf);
    }

    // -----------------------------------------------------------------------
    // ContentStream — stream output
    // -----------------------------------------------------------------------

    public function testContentStreamToPdfIsStream(): void
    {
        $cs = new ContentStream();
        $cs->objectNumber = 1;
        $cs->beginText()->setFont('F1', 12)->showText('Test')->endText();
        $pdf = $cs->toPdf();
        self::assertStringContainsString('stream', $pdf);
        self::assertStringContainsString('endstream', $pdf);
    }

    public function testContentStreamToIndirectObject(): void
    {
        $cs = new ContentStream();
        $cs->objectNumber = 4;
        $cs->generationNumber = 0;
        $cs->showText('Hello');
        $indirect = $cs->toIndirectObject();
        self::assertStringContainsString('4 0 obj', $indirect);
        self::assertStringContainsString('endobj', $indirect);
    }

    public function testFluentChaining(): void
    {
        $cs = new ContentStream();
        $cs->objectNumber = 1;
        // Should return $this for chaining
        $result = $cs
            ->saveGraphicsState()
            ->setLineWidth(1)
            ->setStrokeColorRGB(0, 0, 0)
            ->moveTo(0, 0)
            ->lineTo(100, 100)
            ->stroke()
            ->restoreGraphicsState();
        self::assertSame($cs, $result);
    }

    // -----------------------------------------------------------------------
    // Resources
    // -----------------------------------------------------------------------

    public function testResourcesDefaultProcSet(): void
    {
        $res = new Resources();
        $pdf = $res->toPdf();
        self::assertStringContainsString('/ProcSet', $pdf);
        self::assertStringContainsString('/PDF', $pdf);
        self::assertStringContainsString('/Text', $pdf);
    }

    public function testResourcesAddFont(): void
    {
        $res = new Resources();
        $res->addFont('F1', new PdfReference(5));
        $pdf = $res->toPdf();
        self::assertStringContainsString('/Font', $pdf);
        self::assertStringContainsString('/F1', $pdf);
        self::assertStringContainsString('5 0 R', $pdf);
    }

    public function testResourcesAddXObject(): void
    {
        $res = new Resources();
        $res->addXObject('Im1', new PdfReference(7));
        $pdf = $res->toPdf();
        self::assertStringContainsString('/XObject', $pdf);
        self::assertStringContainsString('/Im1', $pdf);
        self::assertStringContainsString('7 0 R', $pdf);
    }

    public function testResourcesAddExtGState(): void
    {
        $res = new Resources();
        $res->addExtGState('GS1', new PdfReference(9));
        $pdf = $res->toPdf();
        self::assertStringContainsString('/ExtGState', $pdf);
        self::assertStringContainsString('/GS1', $pdf);
        self::assertStringContainsString('9 0 R', $pdf);
    }

    public function testResourcesMultipleFonts(): void
    {
        $res = new Resources();
        $res->addFont('F1', new PdfReference(5));
        $res->addFont('F2', new PdfReference(6));
        $pdf = $res->toPdf();
        self::assertStringContainsString('/F1', $pdf);
        self::assertStringContainsString('/F2', $pdf);
    }

    public function testResourcesCustomProcSet(): void
    {
        $res = new Resources();
        $res->procSet = ['PDF'];
        $pdf = $res->toPdf();
        self::assertStringContainsString('/PDF', $pdf);
    }

    // -----------------------------------------------------------------------
    // Shorthand text operators
    // -----------------------------------------------------------------------

    public function testMoveToNextLineAndShowText(): void
    {
        $cs = new ContentStream();
        $cs->objectNumber = 1;
        $pdf = $cs->moveToNextLineAndShowText('Hello')->toPdf();
        self::assertStringContainsString("(Hello) '", $pdf);
    }

    public function testSetSpacingMoveAndShowText(): void
    {
        $cs = new ContentStream();
        $cs->objectNumber = 1;
        $pdf = $cs->setSpacingMoveAndShowText(2.0, 1.0, 'Word')->toPdf();
        self::assertStringContainsString('(Word) "', $pdf);
        self::assertStringContainsString('2', $pdf);
        self::assertStringContainsString('1', $pdf);
    }

    // -----------------------------------------------------------------------
    // Shading operator
    // -----------------------------------------------------------------------

    public function testPaintShading(): void
    {
        $cs = new ContentStream();
        $cs->objectNumber = 1;
        $pdf = $cs->paintShading('Sh1')->toPdf();
        self::assertStringContainsString('/Sh1 sh', $pdf);
    }

    // -----------------------------------------------------------------------
    // Type 3 glyph operators
    // -----------------------------------------------------------------------

    public function testSetGlyphWidth(): void
    {
        $cs = new ContentStream();
        $cs->objectNumber = 1;
        $pdf = $cs->setGlyphWidth(500.0, 0.0)->toPdf();
        self::assertStringContainsString('d0', $pdf);
        self::assertStringContainsString('500', $pdf);
    }

    public function testSetGlyphWidthAndBoundingBox(): void
    {
        $cs = new ContentStream();
        $cs->objectNumber = 1;
        $pdf = $cs->setGlyphWidthAndBoundingBox(500.0, 0.0, 0.0, -100.0, 500.0, 700.0)->toPdf();
        self::assertStringContainsString('d1', $pdf);
    }

    // -----------------------------------------------------------------------
    // Marked content operators
    // -----------------------------------------------------------------------

    public function testMarkedContentPoint(): void
    {
        $cs = new ContentStream();
        $cs->objectNumber = 1;
        $pdf = $cs->markedContentPoint('Span')->toPdf();
        self::assertStringContainsString('/Span MP', $pdf);
    }

    public function testMarkedContentPointWithProperties(): void
    {
        $cs = new ContentStream();
        $cs->objectNumber = 1;
        $pdf = $cs->markedContentPointWithProperties('Span', '/MC0')->toPdf();
        self::assertStringContainsString('/Span /MC0 DP', $pdf);
    }

    public function testBeginMarkedContent(): void
    {
        $cs = new ContentStream();
        $cs->objectNumber = 1;
        $pdf = $cs->beginMarkedContent('P')->toPdf();
        self::assertStringContainsString('/P BMC', $pdf);
    }

    public function testBeginMarkedContentWithProperties(): void
    {
        $cs = new ContentStream();
        $cs->objectNumber = 1;
        $pdf = $cs->beginMarkedContentWithProperties('P', '/MC0')->toPdf();
        self::assertStringContainsString('/P /MC0 BDC', $pdf);
    }

    public function testEndMarkedContent(): void
    {
        $cs = new ContentStream();
        $cs->objectNumber = 1;
        $pdf = $cs->beginMarkedContent('Artifact')->endMarkedContent()->toPdf();
        self::assertStringContainsString('EMC', $pdf);
    }

    public function testMarkedContentSequence(): void
    {
        $cs = new ContentStream();
        $cs->objectNumber = 1;
        $pdf = $cs->beginMarkedContent('P')->endMarkedContent()->toPdf();
        self::assertStringContainsString('/P BMC', $pdf);
        self::assertStringContainsString('EMC', $pdf);
    }

    // -----------------------------------------------------------------------
    // Compatibility operators
    // -----------------------------------------------------------------------

    public function testBeginEndCompatibility(): void
    {
        $cs = new ContentStream();
        $cs->objectNumber = 1;
        $pdf = $cs->beginCompatibility()->endCompatibility()->toPdf();
        self::assertStringContainsString('BX', $pdf);
        self::assertStringContainsString('EX', $pdf);
    }
}
