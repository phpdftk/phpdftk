<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Tests\ThreeD;

use ApprLabs\Pdf\Core\Graphics\ColorSpace\DeviceRGB;
use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\PdfReference;
use ApprLabs\Pdf\Core\ThreeD\ThreeDBackground;
use ApprLabs\Pdf\Core\ThreeD\ThreeDCrossSection;
use ApprLabs\Pdf\Core\ThreeD\ThreeDLightingScheme;
use ApprLabs\Pdf\Core\ThreeD\ThreeDRenderMode;
use ApprLabs\Pdf\Core\ThreeD\ThreeDStream;
use ApprLabs\Pdf\Core\ThreeD\ThreeDView;
use PHPUnit\Framework\TestCase;

class ThreeDTest extends TestCase
{
    public function testThreeDStream(): void
    {
        $s = new ThreeDStream('U3D', 'fake u3d bytes');
        $s->objectNumber = 1;
        $s->colorSpace = new DeviceRGB();
        $pdf = $s->toIndirectObject();
        self::assertStringContainsString('/Type /3D', $pdf);
        self::assertStringContainsString('/Subtype /U3D', $pdf);
        self::assertStringContainsString('/ColorSpace /DeviceRGB', $pdf);
        self::assertStringContainsString('stream', $pdf);
    }

    public function testThreeDStreamPRC(): void
    {
        $s = new ThreeDStream('PRC');
        $s->objectNumber = 1;
        self::assertStringContainsString('/Subtype /PRC', $s->toPdf());
    }

    public function testThreeDView(): void
    {
        $v = new ThreeDView('Front');
        $v->objectNumber = 1;
        $v->co = 100.0;
        $v->bg = new PdfReference(10);
        $v->rm = new PdfReference(11);
        $v->ls = new PdfReference(12);
        $pdf = $v->toPdf();
        self::assertStringContainsString('/Type /3DView', $pdf);
        self::assertStringContainsString('/XN (Front)', $pdf);
        self::assertStringContainsString('/CO 100', $pdf);
        self::assertStringContainsString('/BG 10 0 R', $pdf);
        self::assertStringContainsString('/RM 11 0 R', $pdf);
        self::assertStringContainsString('/LS 12 0 R', $pdf);
    }

    public function testThreeDBackground(): void
    {
        $bg = new ThreeDBackground();
        $bg->objectNumber = 1;
        $bg->cs = new DeviceRGB();
        $bg->c = new PdfArray([new PdfNumber(1), new PdfNumber(1), new PdfNumber(1)]);
        $bg->ea = false;
        $pdf = $bg->toPdf();
        self::assertStringContainsString('/Type /3DBG', $pdf);
        self::assertStringContainsString('/CS /DeviceRGB', $pdf);
        self::assertStringContainsString('/EA false', $pdf);
    }

    public function testThreeDRenderMode(): void
    {
        $rm = new ThreeDRenderMode('Solid');
        $rm->objectNumber = 1;
        $rm->op = 0.8;
        $pdf = $rm->toPdf();
        self::assertStringContainsString('/Type /3DRenderMode', $pdf);
        self::assertStringContainsString('/Subtype /Solid', $pdf);
        self::assertStringContainsString('/Opacity', $pdf);
    }

    public function testThreeDLightingScheme(): void
    {
        $ls = new ThreeDLightingScheme('Day');
        $ls->objectNumber = 1;
        $pdf = $ls->toPdf();
        self::assertStringContainsString('/Type /3DLightingScheme', $pdf);
        self::assertStringContainsString('/Subtype /Day', $pdf);
    }

    public function testThreeDCrossSection(): void
    {
        $cs = new ThreeDCrossSection();
        $cs->objectNumber = 1;
        $cs->c = new PdfArray([new PdfNumber(0), new PdfNumber(0), new PdfNumber(0)]);
        $cs->o = new PdfArray([new PdfNumber(1), new PdfNumber(0), new PdfNumber(0)]);
        $cs->iv = true;
        $cs->st = false;
        $pdf = $cs->toPdf();
        self::assertStringContainsString('/Type /3DCrossSection', $pdf);
        self::assertStringContainsString('/C', $pdf);
        self::assertStringContainsString('/O', $pdf);
        self::assertStringContainsString('/IV true', $pdf);
        self::assertStringContainsString('/ST false', $pdf);
    }
}
