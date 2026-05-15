<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Tests\Annotation;

use Phpdftk\Pdf\Core\Annotation\BorderEffect;
use Phpdftk\Pdf\Core\Annotation\PolyLineAnnotation;
use Phpdftk\Pdf\Core\Annotation\PolygonAnnotation;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfReference;
use PHPUnit\Framework\TestCase;

class PolygonAndPolyLineTest extends TestCase
{
    private function rect(): PdfArray
    {
        return new PdfArray([
            new PdfNumber(0),
            new PdfNumber(0),
            new PdfNumber(100),
            new PdfNumber(100),
        ]);
    }

    public function testPolygonMinimal(): void
    {
        $a = new PolygonAnnotation($this->rect());
        $pdf = $a->toPdf();
        $this->assertStringContainsString('/Subtype /Polygon', $pdf);
        $this->assertStringNotContainsString('/Vertices', $pdf);
    }

    public function testPolygonAllFields(): void
    {
        $a = new PolygonAnnotation($this->rect());
        $a->vertices = new PdfArray([
            new PdfNumber(10),
            new PdfNumber(20),
            new PdfNumber(30),
            new PdfNumber(40),
        ]);
        $a->le = new PdfArray([new PdfName('None'), new PdfName('Square')]);
        $a->ic = new PdfArray([new PdfNumber(1), new PdfNumber(0), new PdfNumber(0)]);
        $a->be = new BorderEffect();
        $a->it = new PdfName('PolygonCloud');
        $a->measure = new PdfReference(5);

        $pdf = $a->toPdf();
        $this->assertStringContainsString('/Vertices', $pdf);
        $this->assertStringContainsString('/LE', $pdf);
        $this->assertStringContainsString('/IC', $pdf);
        $this->assertStringContainsString('/BE', $pdf);
        $this->assertStringContainsString('/IT /PolygonCloud', $pdf);
        $this->assertStringContainsString('/Measure 5 0 R', $pdf);
    }

    public function testPolyLineMinimal(): void
    {
        $a = new PolyLineAnnotation($this->rect());
        $pdf = $a->toPdf();
        $this->assertStringContainsString('/Subtype /PolyLine', $pdf);
        $this->assertStringNotContainsString('/Vertices', $pdf);
    }

    public function testPolyLineAllFields(): void
    {
        $a = new PolyLineAnnotation($this->rect());
        $a->vertices = new PdfArray([
            new PdfNumber(0),
            new PdfNumber(0),
            new PdfNumber(50),
            new PdfNumber(50),
        ]);
        $a->le = new PdfArray([new PdfName('OpenArrow'), new PdfName('ClosedArrow')]);
        $a->ic = new PdfArray([new PdfNumber(0), new PdfNumber(1), new PdfNumber(0)]);
        $a->be = new BorderEffect();
        $a->it = new PdfName('PolyLineDimension');
        $a->measure = new PdfReference(7);

        $pdf = $a->toPdf();
        $this->assertStringContainsString('/Vertices', $pdf);
        $this->assertStringContainsString('/LE', $pdf);
        $this->assertStringContainsString('/IC', $pdf);
        $this->assertStringContainsString('/BE', $pdf);
        $this->assertStringContainsString('/IT /PolyLineDimension', $pdf);
        $this->assertStringContainsString('/Measure 7 0 R', $pdf);
    }

    public function testSubtypeAccessors(): void
    {
        $polygon = new PolygonAnnotation($this->rect());
        $polyline = new PolyLineAnnotation($this->rect());
        $this->assertSame('Polygon', $polygon->getSubtype());
        $this->assertSame('PolyLine', $polyline->getSubtype());
    }
}
