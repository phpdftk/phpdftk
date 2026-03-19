<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Tests\Graphics;

use PHPUnit\Framework\TestCase;
use ApprLabs\Pdf\Core\Graphics\ColorSpace\DeviceCMYK;
use ApprLabs\Pdf\Core\Graphics\ColorSpace\DeviceGray;
use ApprLabs\Pdf\Core\Graphics\ColorSpace\DeviceRGB;
use ApprLabs\Pdf\Core\Graphics\ExtGState;
use ApprLabs\Pdf\Core\Graphics\XObject\FormXObject;
use ApprLabs\Pdf\Core\Graphics\XObject\ImageXObject;
use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfNumber;

class GraphicsTest extends TestCase
{
    // -----------------------------------------------------------------------
    // ColorSpace
    // -----------------------------------------------------------------------

    public function testDeviceRGB(): void
    {
        $cs = new DeviceRGB();
        self::assertSame('/DeviceRGB', $cs->toPdf());
    }

    public function testDeviceCMYK(): void
    {
        $cs = new DeviceCMYK();
        self::assertSame('/DeviceCMYK', $cs->toPdf());
    }

    public function testDeviceGray(): void
    {
        $cs = new DeviceGray();
        self::assertSame('/DeviceGray', $cs->toPdf());
    }

    // -----------------------------------------------------------------------
    // ExtGState
    // -----------------------------------------------------------------------

    public function testExtGStateType(): void
    {
        $gs = new ExtGState();
        $gs->objectNumber = 1;
        $pdf = $gs->toPdf();
        self::assertStringContainsString('/Type /ExtGState', $pdf);
    }

    public function testExtGStateLineWidth(): void
    {
        $gs = new ExtGState();
        $gs->objectNumber = 1;
        $gs->lw = 2.5;
        $pdf = $gs->toPdf();
        self::assertStringContainsString('/LW 2.5', $pdf);
    }

    public function testExtGStateLineCap(): void
    {
        $gs = new ExtGState();
        $gs->objectNumber = 1;
        $gs->lc = 1;
        $pdf = $gs->toPdf();
        self::assertStringContainsString('/LC 1', $pdf);
    }

    public function testExtGStateLineJoin(): void
    {
        $gs = new ExtGState();
        $gs->objectNumber = 1;
        $gs->lj = 2;
        $pdf = $gs->toPdf();
        self::assertStringContainsString('/LJ 2', $pdf);
    }

    public function testExtGStateMiterLimit(): void
    {
        $gs = new ExtGState();
        $gs->objectNumber = 1;
        $gs->ml = 10.0;
        $pdf = $gs->toPdf();
        self::assertStringContainsString('/ML 10', $pdf);
    }

    public function testExtGStateStrokeAlpha(): void
    {
        $gs = new ExtGState();
        $gs->objectNumber = 1;
        $gs->ca = 0.5;
        $pdf = $gs->toPdf();
        self::assertStringContainsString('/CA 0.5', $pdf);
    }

    public function testExtGStateFillAlpha(): void
    {
        $gs = new ExtGState();
        $gs->objectNumber = 1;
        $gs->caLower = 0.8;
        $pdf = $gs->toPdf();
        self::assertStringContainsString('/ca 0.8', $pdf);
    }

    public function testExtGStateOverprintStroke(): void
    {
        $gs = new ExtGState();
        $gs->objectNumber = 1;
        $gs->op = true;
        $pdf = $gs->toPdf();
        self::assertStringContainsString('/OP true', $pdf);
    }

    public function testExtGStateOverprintFill(): void
    {
        $gs = new ExtGState();
        $gs->objectNumber = 1;
        $gs->opLower = false;
        $pdf = $gs->toPdf();
        self::assertStringContainsString('/op false', $pdf);
    }

    public function testExtGStateBlendMode(): void
    {
        $gs = new ExtGState();
        $gs->objectNumber = 1;
        $gs->bm = 'Multiply';
        $pdf = $gs->toPdf();
        self::assertStringContainsString('/BM /Multiply', $pdf);
    }

    public function testExtGStateSoftMask(): void
    {
        $gs = new ExtGState();
        $gs->objectNumber = 1;
        $gs->sMask = 'None';
        $pdf = $gs->toPdf();
        self::assertStringContainsString('/SMask /None', $pdf);
    }

    public function testExtGStateRenderingIntent(): void
    {
        $gs = new ExtGState();
        $gs->objectNumber = 1;
        $gs->ri = new PdfName('RelativeColorimetric');
        $pdf = $gs->toPdf();
        self::assertStringContainsString('/RI /RelativeColorimetric', $pdf);
    }

    public function testExtGStateDashPattern(): void
    {
        $gs = new ExtGState();
        $gs->objectNumber = 1;
        $gs->d = new PdfArray([
            new PdfArray([new PdfNumber(3), new PdfNumber(1)]),
            new PdfNumber(0),
        ]);
        $pdf = $gs->toPdf();
        self::assertStringContainsString('/D', $pdf);
    }

    public function testExtGStateTextKnockout(): void
    {
        $gs = new ExtGState();
        $gs->objectNumber = 1;
        $gs->tk = true;
        $pdf = $gs->toPdf();
        self::assertStringContainsString('/TK true', $pdf);
    }

    public function testExtGStateAlphaIsShape(): void
    {
        $gs = new ExtGState();
        $gs->objectNumber = 1;
        $gs->ais = false;
        $pdf = $gs->toPdf();
        self::assertStringContainsString('/AIS false', $pdf);
    }

    public function testExtGStateStrokeAdjustment(): void
    {
        $gs = new ExtGState();
        $gs->objectNumber = 1;
        $gs->sa = true;
        $pdf = $gs->toPdf();
        self::assertStringContainsString('/SA true', $pdf);
    }

    public function testExtGStateOPM(): void
    {
        $gs = new ExtGState();
        $gs->objectNumber = 1;
        $gs->opm = 1;
        $pdf = $gs->toPdf();
        self::assertStringContainsString('/OPM 1', $pdf);
    }

    public function testExtGStateFlatness(): void
    {
        $gs = new ExtGState();
        $gs->objectNumber = 1;
        $gs->fl = 1.0;
        $pdf = $gs->toPdf();
        self::assertStringContainsString('/FL 1', $pdf);
    }

    public function testExtGStateSmoothness(): void
    {
        $gs = new ExtGState();
        $gs->objectNumber = 1;
        $gs->sm = 0.01;
        $pdf = $gs->toPdf();
        self::assertStringContainsString('/SM', $pdf);
    }

    public function testExtGStateToIndirectObject(): void
    {
        $gs = new ExtGState();
        $gs->objectNumber = 3;
        $gs->generationNumber = 0;
        $indirect = $gs->toIndirectObject();
        self::assertStringContainsString('3 0 obj', $indirect);
        self::assertStringContainsString('endobj', $indirect);
    }

    public function testExtGStateEmpty(): void
    {
        $gs = new ExtGState();
        $gs->objectNumber = 1;
        $pdf = $gs->toPdf();
        // Should just have type
        self::assertStringContainsString('/Type /ExtGState', $pdf);
    }

    // -----------------------------------------------------------------------
    // ImageXObject
    // -----------------------------------------------------------------------

    public function testImageXObjectType(): void
    {
        $img = new ImageXObject(100, 100, 'DeviceRGB');
        $img->objectNumber = 1;
        $pdf = $img->toPdf();
        self::assertStringContainsString('/Type /XObject', $pdf);
        self::assertStringContainsString('/Subtype /Image', $pdf);
    }

    public function testImageXObjectDimensions(): void
    {
        $img = new ImageXObject(800, 600, 'DeviceRGB');
        $img->objectNumber = 1;
        $pdf = $img->toPdf();
        self::assertStringContainsString('/Width 800', $pdf);
        self::assertStringContainsString('/Height 600', $pdf);
    }

    public function testImageXObjectColorSpace(): void
    {
        $img = new ImageXObject(100, 100, 'DeviceRGB');
        $img->objectNumber = 1;
        $pdf = $img->toPdf();
        self::assertStringContainsString('/ColorSpace /DeviceRGB', $pdf);
    }

    public function testImageXObjectColorSpaceObject(): void
    {
        $img = new ImageXObject(100, 100, new DeviceGray());
        $img->objectNumber = 1;
        $pdf = $img->toPdf();
        self::assertStringContainsString('/ColorSpace /DeviceGray', $pdf);
    }

    public function testImageXObjectBitsPerComponent(): void
    {
        $img = new ImageXObject(100, 100, 'DeviceRGB', 8);
        $img->objectNumber = 1;
        $pdf = $img->toPdf();
        self::assertStringContainsString('/BitsPerComponent 8', $pdf);
    }

    public function testImageXObjectWithFilter(): void
    {
        $img = new ImageXObject(100, 100, 'DeviceRGB');
        $img->objectNumber = 1;
        $img->filter = new PdfName('DCTDecode');
        $pdf = $img->toPdf();
        self::assertStringContainsString('/Filter /DCTDecode', $pdf);
    }

    public function testImageXObjectWithImageMask(): void
    {
        $img = new ImageXObject(10, 10, 'DeviceGray', 1);
        $img->objectNumber = 1;
        $img->imageMask = true;
        $pdf = $img->toPdf();
        self::assertStringContainsString('/ImageMask true', $pdf);
    }

    public function testImageXObjectWithInterpolate(): void
    {
        $img = new ImageXObject(100, 100, 'DeviceRGB');
        $img->objectNumber = 1;
        $img->interpolate = true;
        $pdf = $img->toPdf();
        self::assertStringContainsString('/Interpolate true', $pdf);
    }

    // -----------------------------------------------------------------------
    // FormXObject
    // -----------------------------------------------------------------------

    public function testFormXObjectType(): void
    {
        $bBox = new PdfArray([
            new PdfNumber(0), new PdfNumber(0),
            new PdfNumber(100), new PdfNumber(100),
        ]);
        $form = new FormXObject($bBox);
        $form->objectNumber = 1;
        $pdf = $form->toPdf();
        self::assertStringContainsString('/Type /XObject', $pdf);
        self::assertStringContainsString('/Subtype /Form', $pdf);
    }

    public function testFormXObjectBBox(): void
    {
        $bBox = new PdfArray([
            new PdfNumber(0), new PdfNumber(0),
            new PdfNumber(200), new PdfNumber(150),
        ]);
        $form = new FormXObject($bBox);
        $form->objectNumber = 1;
        $pdf = $form->toPdf();
        self::assertStringContainsString('/BBox', $pdf);
    }

    public function testFormXObjectWithContent(): void
    {
        $bBox = new PdfArray([
            new PdfNumber(0), new PdfNumber(0),
            new PdfNumber(100), new PdfNumber(100),
        ]);
        $form = new FormXObject($bBox, "1 0 0 RG 0 0 100 100 re S");
        $form->objectNumber = 1;
        $pdf = $form->toPdf();
        self::assertStringContainsString('stream', $pdf);
        self::assertStringContainsString('endstream', $pdf);
    }

    public function testFormXObjectWithMatrix(): void
    {
        $bBox = new PdfArray([
            new PdfNumber(0), new PdfNumber(0),
            new PdfNumber(100), new PdfNumber(100),
        ]);
        $form = new FormXObject($bBox);
        $form->objectNumber = 1;
        $form->matrix = new PdfArray([
            new PdfNumber(1), new PdfNumber(0),
            new PdfNumber(0), new PdfNumber(1),
            new PdfNumber(0), new PdfNumber(0),
        ]);
        $pdf = $form->toPdf();
        self::assertStringContainsString('/Matrix', $pdf);
    }
}
