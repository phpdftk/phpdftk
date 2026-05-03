<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Tests\ThreeD;

use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfString;
use Phpdftk\Pdf\Core\ThreeD\ThreeDMeasure;
use Phpdftk\Pdf\Core\ThreeD\ThreeDNode;
use PHPUnit\Framework\TestCase;

class ThreeDNodeAndMeasureTest extends TestCase
{
    public function testThreeDNode(): void
    {
        $n = new ThreeDNode('Camera');
        $n->objectNumber = 1;
        $n->v = true;
        $n->o = 0.8;
        $pdf = $n->toPdf();
        self::assertStringContainsString('/Type /3DNode', $pdf);
        self::assertStringContainsString('/N (Camera)', $pdf);
        self::assertStringContainsString('/V true', $pdf);
        self::assertStringContainsString('/O 0.8', $pdf);
    }

    public function testThreeDMeasureLinearDistance(): void
    {
        $m = new ThreeDMeasure('LD');
        $m->objectNumber = 1;
        $m->text = new PdfString('5.0 m');
        $m->anchors = new PdfArray([new PdfNumber(0), new PdfNumber(0), new PdfNumber(0)]);
        $pdf = $m->toPdf();
        self::assertStringContainsString('/Type /3DMeasure', $pdf);
        self::assertStringContainsString('/Subtype /LD', $pdf);
        self::assertStringContainsString('/V (5.0 m)', $pdf);
    }

    public function testThreeDMeasureSubtypes(): void
    {
        foreach (['3DC', 'LD', 'PD3', 'RD3', 'AD3', '3DM'] as $subtype) {
            $m = new ThreeDMeasure($subtype);
            $m->objectNumber = 1;
            self::assertStringContainsString('/Subtype /' . $subtype, $m->toPdf());
        }
    }
}
