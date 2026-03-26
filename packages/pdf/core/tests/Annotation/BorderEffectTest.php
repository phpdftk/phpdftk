<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Tests\Annotation;

use PHPUnit\Framework\TestCase;
use ApprLabs\Pdf\Core\Annotation\BorderEffect;
use ApprLabs\Pdf\Core\Annotation\FreeTextAnnotation;
use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\PdfString;

class BorderEffectTest extends TestCase
{
    public function testEmptyBorderEffectProducesEmptyDict(): void
    {
        $be = new BorderEffect();
        $pdf = $be->toPdf();
        self::assertStringContainsString('<<', $pdf);
        self::assertStringNotContainsString('/S', $pdf);
        self::assertStringNotContainsString('/I', $pdf);
    }

    public function testStyleNone(): void
    {
        $be = new BorderEffect();
        $be->s = new PdfName('S');
        self::assertStringContainsString('/S /S', $be->toPdf());
    }

    public function testStyleCloudy(): void
    {
        $be = new BorderEffect();
        $be->s = new PdfName('C');
        self::assertStringContainsString('/S /C', $be->toPdf());
    }

    public function testIntensity(): void
    {
        $be = new BorderEffect();
        $be->s = new PdfName('C');
        $be->i = new PdfNumber(2.0);
        $pdf = $be->toPdf();
        self::assertStringContainsString('/S /C', $pdf);
        self::assertStringContainsString('/I 2', $pdf);
    }

    public function testAssignedToFreeTextAnnotation(): void
    {
        $be = new BorderEffect();
        $be->s = new PdfName('C');
        $be->i = new PdfNumber(1.0);

        $rect = new PdfArray([new PdfNumber(72), new PdfNumber(700), new PdfNumber(300), new PdfNumber(720)]);
        $annot = new FreeTextAnnotation($rect, new PdfString('/Helvetica 12 Tf 0 g'));
        $annot->objectNumber = 1;
        $annot->be = $be;

        $pdf = $annot->toPdf();
        self::assertStringContainsString('/BE', $pdf);
        self::assertStringContainsString('/S /C', $pdf);
    }
}
