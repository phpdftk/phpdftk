<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Tests\Graphics;

use Phpdftk\Pdf\Core\Content\Resources;
use Phpdftk\Pdf\Core\Graphics\Pattern\ShadingPattern;
use Phpdftk\Pdf\Core\Graphics\Pattern\TilingPattern;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfReference;
use PHPUnit\Framework\TestCase;

class PatternTest extends TestCase
{
    public function testTilingPattern(): void
    {
        $bbox = new PdfArray([
            new PdfNumber(0), new PdfNumber(0),
            new PdfNumber(20), new PdfNumber(20),
        ]);
        $p = new TilingPattern(
            paintType: 1,
            tilingType: 1,
            bbox: $bbox,
            xStep: 20,
            yStep: 20,
            resources: new Resources(),
            contentStream: '1 0 0 rg 0 0 20 20 re f',
        );
        $p->objectNumber = 1;
        $pdf = $p->toIndirectObject();
        self::assertStringContainsString('/Type /Pattern', $pdf);
        self::assertStringContainsString('/PatternType 1', $pdf);
        self::assertStringContainsString('/PaintType 1', $pdf);
        self::assertStringContainsString('/TilingType 1', $pdf);
        self::assertStringContainsString('/XStep 20', $pdf);
        self::assertStringContainsString('/YStep 20', $pdf);
        self::assertStringContainsString('stream', $pdf);
        self::assertStringContainsString('1 0 0 rg', $pdf);
        self::assertSame(1, $p->getPatternType());
    }

    public function testShadingPattern(): void
    {
        $p = new ShadingPattern(new PdfReference(15));
        $p->objectNumber = 1;
        $p->matrix = new PdfArray([
            new PdfNumber(1), new PdfNumber(0), new PdfNumber(0),
            new PdfNumber(1), new PdfNumber(0), new PdfNumber(0),
        ]);
        $pdf = $p->toPdf();
        self::assertStringContainsString('/Type /Pattern', $pdf);
        self::assertStringContainsString('/PatternType 2', $pdf);
        self::assertStringContainsString('/Shading 15 0 R', $pdf);
        self::assertStringContainsString('/Matrix', $pdf);
        self::assertSame(2, $p->getPatternType());
    }
}
