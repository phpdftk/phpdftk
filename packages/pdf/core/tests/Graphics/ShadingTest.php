<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Tests\Graphics;

use Phpdftk\Pdf\Core\Graphics\ColorSpace\DeviceRGB;
use Phpdftk\Pdf\Core\Graphics\Shading\ShadingType1;
use Phpdftk\Pdf\Core\Graphics\Shading\ShadingType2;
use Phpdftk\Pdf\Core\Graphics\Shading\ShadingType3;
use Phpdftk\Pdf\Core\Graphics\Shading\ShadingType4;
use Phpdftk\Pdf\Core\Graphics\Shading\ShadingType5;
use Phpdftk\Pdf\Core\Graphics\Shading\ShadingType6;
use Phpdftk\Pdf\Core\Graphics\Shading\ShadingType7;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfReference;
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
            new PdfReference(10),
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
            new PdfReference(10),
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

    public function testMeshShadingBackgroundAndBBoxAndAntiAlias(): void
    {
        $shading = new \Phpdftk\Pdf\Core\Graphics\Shading\ShadingType4(
            new DeviceRGB(),
            bitsPerCoordinate: 16,
            bitsPerComponent: 8,
            bitsPerFlag: 8,
            decode: $this->decode(),
        );
        $shading->background = new \Phpdftk\Pdf\Core\PdfArray([
            new \Phpdftk\Pdf\Core\PdfNumber(0),
            new \Phpdftk\Pdf\Core\PdfNumber(0),
            new \Phpdftk\Pdf\Core\PdfNumber(0),
        ]);
        $shading->bbox = new \Phpdftk\Pdf\Core\PdfArray([
            new \Phpdftk\Pdf\Core\PdfNumber(0),
            new \Phpdftk\Pdf\Core\PdfNumber(0),
            new \Phpdftk\Pdf\Core\PdfNumber(100),
            new \Phpdftk\Pdf\Core\PdfNumber(100),
        ]);
        $shading->antiAlias = false;
        $shading->function = new \Phpdftk\Pdf\Core\PdfReference(99);
        $shading->objectNumber = 1;
        $pdf = $shading->toIndirectObject();
        self::assertStringContainsString('/Background', $pdf);
        self::assertStringContainsString('/BBox', $pdf);
        self::assertStringContainsString('/AntiAlias false', $pdf);
        self::assertStringContainsString('/Function 99 0 R', $pdf);
    }

    public function testShadingBackgroundAndBBoxAndAntiAlias(): void
    {
        $s = new \Phpdftk\Pdf\Core\Graphics\Shading\ShadingType2(
            new DeviceRGB(),
            coords: new \Phpdftk\Pdf\Core\PdfArray([
                new \Phpdftk\Pdf\Core\PdfNumber(0),
                new \Phpdftk\Pdf\Core\PdfNumber(0),
                new \Phpdftk\Pdf\Core\PdfNumber(100),
                new \Phpdftk\Pdf\Core\PdfNumber(0),
            ]),
            function: new \Phpdftk\Pdf\Core\PdfReference(99),
        );
        $s->background = new \Phpdftk\Pdf\Core\PdfArray([
            new \Phpdftk\Pdf\Core\PdfNumber(1),
            new \Phpdftk\Pdf\Core\PdfNumber(1),
            new \Phpdftk\Pdf\Core\PdfNumber(1),
        ]);
        $s->bbox = new \Phpdftk\Pdf\Core\PdfArray([
            new \Phpdftk\Pdf\Core\PdfNumber(0),
            new \Phpdftk\Pdf\Core\PdfNumber(0),
            new \Phpdftk\Pdf\Core\PdfNumber(100),
            new \Phpdftk\Pdf\Core\PdfNumber(100),
        ]);
        $s->antiAlias = true;
        $s->objectNumber = 1;
        $pdf = $s->toIndirectObject();
        self::assertStringContainsString('/Background', $pdf);
        self::assertStringContainsString('/BBox', $pdf);
        self::assertStringContainsString('/AntiAlias true', $pdf);
    }
}
