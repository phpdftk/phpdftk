<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Tests\ThreeD;

use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfString;
use Phpdftk\Pdf\Core\ThreeD\ThreeDRenderMode;
use Phpdftk\Pdf\Core\ThreeD\ThreeDView;
use PHPUnit\Framework\TestCase;

class ThreeDViewAndRenderModeTest extends TestCase
{
    public function testThreeDViewMinimal(): void
    {
        $v = new ThreeDView('Default View');
        $pdf = $v->toPdf();
        $this->assertStringContainsString('/Type /3DView', $pdf);
        $this->assertStringContainsString('/XN', $pdf);
        $this->assertStringNotContainsString('/IN', $pdf);
    }

    public function testThreeDViewAllFields(): void
    {
        $v = new ThreeDView('Default View');
        $v->in = new PdfString('internal-name');
        $v->ms = new PdfName('M');
        $v->c2w = new PdfArray([new PdfNumber(1), new PdfNumber(0), new PdfNumber(0), new PdfNumber(1)]);
        $v->co = 12.5;
        $v->p = new PdfDictionary();
        $v->o = new PdfArray([]);
        $v->bg = new PdfReference(99);
        $v->rm = new PdfReference(98);
        $v->ls = new PdfReference(97);
        $v->sa = new PdfArray([]);
        $v->na = new PdfArray([]);
        $v->nr = true;

        $pdf = $v->toPdf();
        foreach (['/IN', '/MS /M', '/C2W', '/CO 12.5', '/P', '/O', '/BG 99 0 R', '/RM 98 0 R', '/LS 97 0 R', '/SA', '/NA', '/NR true'] as $needle) {
            $this->assertStringContainsString($needle, $pdf);
        }
    }

    public function testThreeDRenderModeMinimal(): void
    {
        $r = new ThreeDRenderMode('Solid');
        $pdf = $r->toPdf();
        $this->assertStringContainsString('/Type /3DRenderMode', $pdf);
        $this->assertStringContainsString('/Subtype /Solid', $pdf);
    }

    public function testThreeDRenderModeAllFields(): void
    {
        $r = new ThreeDRenderMode('Transparent');
        $r->ac = new PdfArray([new PdfNumber(1), new PdfNumber(0), new PdfNumber(0)]);
        $r->fc = new PdfArray([new PdfNumber(0), new PdfNumber(1), new PdfNumber(0)]);
        $r->op = 0.7;
        $r->cv = true;

        $pdf = $r->toPdf();
        $this->assertStringContainsString('/AC', $pdf);
        $this->assertStringContainsString('/FC', $pdf);
        $this->assertStringContainsString('/Opacity 0.7', $pdf);
        $this->assertStringContainsString('/CV true', $pdf);
    }
}
