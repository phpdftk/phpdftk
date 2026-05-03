<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Tests\Annotation;

use PHPUnit\Framework\TestCase;
use Phpdftk\Pdf\Core\Annotation\BorderStyle;
use Phpdftk\Pdf\Core\Annotation\LinkAnnotation;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;

class BorderStyleTest extends TestCase
{
    public function testBorderStyleType(): void
    {
        $bs = new BorderStyle();
        self::assertStringContainsString('/Type /Border', $bs->toPdf());
    }

    public function testBorderStyleSolid(): void
    {
        $bs = new BorderStyle();
        $bs->w = new PdfNumber(1.0);
        $bs->s = new PdfName('S');
        $pdf = $bs->toPdf();
        self::assertStringContainsString('/W', $pdf);
        self::assertStringContainsString('/S /S', $pdf);
    }

    public function testBorderStyleDashed(): void
    {
        $bs = new BorderStyle();
        $bs->w = new PdfNumber(2.0);
        $bs->s = new PdfName('D');
        $bs->d = new PdfArray([new PdfNumber(3), new PdfNumber(2)]);
        $pdf = $bs->toPdf();
        self::assertStringContainsString('/S /D', $pdf);
        self::assertStringContainsString('/D', $pdf);
    }

    public function testBorderStyleBeveled(): void
    {
        $bs = new BorderStyle();
        $bs->s = new PdfName('B');
        self::assertStringContainsString('/S /B', $bs->toPdf());
    }

    public function testBorderStyleInset(): void
    {
        $bs = new BorderStyle();
        $bs->s = new PdfName('I');
        self::assertStringContainsString('/S /I', $bs->toPdf());
    }

    public function testBorderStyleUnderline(): void
    {
        $bs = new BorderStyle();
        $bs->s = new PdfName('U');
        self::assertStringContainsString('/S /U', $bs->toPdf());
    }

    public function testBorderStyleEmptyOmitsOptionalFields(): void
    {
        $bs = new BorderStyle();
        $pdf = $bs->toPdf();
        self::assertStringNotContainsString('/W', $pdf);
        self::assertStringNotContainsString('/S', $pdf);
        self::assertStringNotContainsString('/D', $pdf);
    }

    public function testBorderStyleOnAnnotation(): void
    {
        $bs = new BorderStyle();
        $bs->w = new PdfNumber(2.0);
        $bs->s = new PdfName('S');

        $rect = new PdfArray([new PdfNumber(72), new PdfNumber(700), new PdfNumber(200), new PdfNumber(720)]);
        $link = new LinkAnnotation($rect);
        $link->objectNumber = 1;
        $link->bs = $bs;

        $pdf = $link->toPdf();
        self::assertStringContainsString('/BS', $pdf);
        self::assertStringContainsString('/Type /Border', $pdf);
    }
}
