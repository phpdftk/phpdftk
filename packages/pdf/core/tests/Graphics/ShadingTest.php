<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Tests\Graphics;

use ApprLabs\Pdf\Core\Graphics\ColorSpace\DeviceRGB;
use ApprLabs\Pdf\Core\Graphics\Shading\ShadingType1;
use ApprLabs\Pdf\Core\Graphics\Shading\ShadingType2;
use ApprLabs\Pdf\Core\Graphics\Shading\ShadingType3;
use ApprLabs\Pdf\Core\Graphics\Shading\ShadingType4;
use ApprLabs\Pdf\Core\Graphics\Shading\ShadingType5;
use ApprLabs\Pdf\Core\Graphics\Shading\ShadingType6;
use ApprLabs\Pdf\Core\Graphics\Shading\ShadingType7;
use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\PdfReference;
use PHPUnit\Framework\TestCase;

class ShadingTest extends TestCase
{
    private function decode(): PdfArray
    {
        return new PdfArray([
            new PdfNumber(0), new PdfNumber(1),
            new PdfNumber(0), new PdfNumber(1),
            new PdfNumber(0), new PdfNumber(1),
            new PdfNumber(0), new PdfNumber(1),
            new PdfNumber(0), new PdfNumber(1),
        ]);
    }

    public function testShadingType1(): void
    {
        $s = new ShadingType1(new DeviceRGB(), new PdfReference(10));
        $s->objectNumber = 1;
        $pdf = $s->toPdf();
        self::assertStringContainsString('/ShadingType 1', $pdf);
        self::assertStringContainsString('/Function 10 0 R', $pdf);
    }

    public function testShadingType2Axial(): void
    {
        $s = new ShadingType2(
            new DeviceRGB(),
            new PdfArray([new PdfNumber(0), new PdfNumber(0), new PdfNumber(200), new PdfNumber(0)]),
            new PdfReference(10)
        );
        $s->objectNumber = 1;
        $pdf = $s->toPdf();
        self::assertStringContainsString('/ShadingType 2', $pdf);
        self::assertStringContainsString('/Coords', $pdf);
        self::assertStringContainsString('/Function 10 0 R', $pdf);
    }

    public function testShadingType3Radial(): void
    {
        $s = new ShadingType3(
            new DeviceRGB(),
            new PdfArray([
                new PdfNumber(100), new PdfNumber(100), new PdfNumber(0),
                new PdfNumber(100), new PdfNumber(100), new PdfNumber(50),
            ]),
            new PdfReference(10)
        );
        $s->objectNumber = 1;
        $pdf = $s->toPdf();
        self::assertStringContainsString('/ShadingType 3', $pdf);
        self::assertStringContainsString('/Coords', $pdf);
    }

    public function testShadingType4(): void
    {
        $s = new ShadingType4(new DeviceRGB(), 16, 8, 8, $this->decode());
        $s->objectNumber = 1;
        $pdf = $s->toIndirectObject();
        self::assertStringContainsString('/ShadingType 4', $pdf);
        self::assertStringContainsString('/BitsPerFlag 8', $pdf);
        self::assertStringContainsString('stream', $pdf);
    }

    public function testShadingType5(): void
    {
        $s = new ShadingType5(new DeviceRGB(), 16, 8, 4, $this->decode());
        $s->objectNumber = 1;
        $pdf = $s->toIndirectObject();
        self::assertStringContainsString('/ShadingType 5', $pdf);
        self::assertStringContainsString('/VerticesPerRow 4', $pdf);
    }

    public function testShadingType6(): void
    {
        $s = new ShadingType6(new DeviceRGB(), 16, 8, 8, $this->decode());
        $s->objectNumber = 1;
        $pdf = $s->toIndirectObject();
        self::assertStringContainsString('/ShadingType 6', $pdf);
        self::assertStringContainsString('/BitsPerFlag 8', $pdf);
    }

    public function testShadingType7(): void
    {
        $s = new ShadingType7(new DeviceRGB(), 16, 8, 8, $this->decode());
        $s->objectNumber = 1;
        $pdf = $s->toIndirectObject();
        self::assertStringContainsString('/ShadingType 7', $pdf);
    }
}
