<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Tests\Annotation;

use Phpdftk\Pdf\Core\Annotation\BorderEffect;
use Phpdftk\Pdf\Core\Annotation\FreeTextAnnotation;
use Phpdftk\Pdf\Core\Annotation\LinkAnnotation;
use Phpdftk\Pdf\Core\Annotation\SquareAnnotation;
use Phpdftk\Pdf\Core\Annotation\WidgetAnnotation;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfString;
use PHPUnit\Framework\TestCase;

class SmallAnnotationsTest extends TestCase
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

    public function testSquareMinimalAndFull(): void
    {
        $sq = new SquareAnnotation($this->rect());
        $pdf = $sq->toPdf();
        $this->assertStringContainsString('/Subtype /Square', $pdf);

        $sq2 = new SquareAnnotation($this->rect());
        $sq2->ic = new PdfArray([new PdfNumber(1)]);
        $sq2->be = new BorderEffect();
        $sq2->rd = new PdfArray([new PdfNumber(0), new PdfNumber(0), new PdfNumber(0), new PdfNumber(0)]);
        $sq2->measure = new PdfReference(99);
        $pdf2 = $sq2->toPdf();
        $this->assertStringContainsString('/IC', $pdf2);
        $this->assertStringContainsString('/BE', $pdf2);
        $this->assertStringContainsString('/RD', $pdf2);
        $this->assertStringContainsString('/Measure 99 0 R', $pdf2);
    }

    public function testLinkMinimalAndFull(): void
    {
        $link = new LinkAnnotation($this->rect());
        $this->assertSame('Link', $link->getSubtype());
        $this->assertStringContainsString('/Subtype /Link', $link->toPdf());

        $link2 = new LinkAnnotation($this->rect());
        $link2->dest = new PdfReference(1);
        $link2->a = new PdfDictionary();
        $link2->pa = new PdfDictionary();
        $link2->quadPoints = new PdfArray([new PdfNumber(1)]);
        $link2->h = new PdfName('I');

        $pdf = $link2->toPdf();
        $this->assertStringContainsString('/Dest 1 0 R', $pdf);
        $this->assertStringContainsString('/A', $pdf);
        $this->assertStringContainsString('/PA', $pdf);
        $this->assertStringContainsString('/QuadPoints', $pdf);
        $this->assertStringContainsString('/H /I', $pdf);
    }

    public function testWidgetMinimalAndFull(): void
    {
        $w = new WidgetAnnotation($this->rect());
        $this->assertSame('Widget', $w->getSubtype());
        $this->assertStringContainsString('/Subtype /Widget', $w->toPdf());

        $w2 = new WidgetAnnotation($this->rect());
        $w2->h = new PdfName('N');
        $w2->mk = new PdfReference(2);
        $w2->a = new PdfReference(3);
        $w2->aa = new PdfReference(4);
        $w2->parent = new PdfReference(5);

        $pdf = $w2->toPdf();
        $this->assertStringContainsString('/H /N', $pdf);
        $this->assertStringContainsString('/MK 2 0 R', $pdf);
        $this->assertStringContainsString('/A 3 0 R', $pdf);
        $this->assertStringContainsString('/AA 4 0 R', $pdf);
        $this->assertStringContainsString('/Parent 5 0 R', $pdf);
    }

    public function testFreeTextRequiresDa(): void
    {
        $da = new PdfString('/Helv 12 Tf 0 0 0 rg');
        $ft = new FreeTextAnnotation($this->rect(), $da);
        $this->assertSame('FreeText', $ft->getSubtype());
        $pdf = $ft->toPdf();
        $this->assertStringContainsString('/Subtype /FreeText', $pdf);
        $this->assertStringContainsString('/DA', $pdf);
    }

    public function testFreeTextAllOptionalFields(): void
    {
        $ft = new FreeTextAnnotation($this->rect(), new PdfString('/Helv 12 Tf 0 0 0 rg'));
        $ft->q = 1;
        $ft->rc = new PdfString('<body>rich</body>');
        $ft->ds = new PdfString('font:Helv 12pt');
        $ft->cl = new PdfReference(7);
        $ft->it = new PdfName('FreeTextCallout');
        $ft->be = new BorderEffect();
        $ft->rd = new PdfArray([new PdfNumber(0), new PdfNumber(0), new PdfNumber(0), new PdfNumber(0)]);
        $ft->le = new PdfName('OpenArrow');

        $pdf = $ft->toPdf();
        $this->assertStringContainsString('/Q 1', $pdf);
        $this->assertStringContainsString('/RC', $pdf);
        $this->assertStringContainsString('/DS', $pdf);
        $this->assertStringContainsString('/CL 7 0 R', $pdf);
        $this->assertStringContainsString('/IT /FreeTextCallout', $pdf);
        $this->assertStringContainsString('/BE', $pdf);
        $this->assertStringContainsString('/RD', $pdf);
        $this->assertStringContainsString('/LE /OpenArrow', $pdf);
    }
}
