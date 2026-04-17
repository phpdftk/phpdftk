<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Tests\Graphics;

use ApprLabs\Pdf\Core\Graphics\Function\FunctionType0;
use ApprLabs\Pdf\Core\Graphics\Function\FunctionType2;
use ApprLabs\Pdf\Core\Graphics\Function\FunctionType3;
use ApprLabs\Pdf\Core\Graphics\Function\FunctionType4;
use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\PdfReference;
use PHPUnit\Framework\TestCase;

class FunctionTest extends TestCase
{
    public function testFunctionType2Simple(): void
    {
        $f = new FunctionType2(
            domain: new PdfArray([new PdfNumber(0), new PdfNumber(1)]),
            c0: new PdfArray([new PdfNumber(0), new PdfNumber(0), new PdfNumber(0)]),
            c1: new PdfArray([new PdfNumber(1), new PdfNumber(1), new PdfNumber(1)]),
            n: 1.0
        );
        $f->objectNumber = 1;
        $pdf = $f->toPdf();
        self::assertStringContainsString('/FunctionType 2', $pdf);
        self::assertStringContainsString('/Domain', $pdf);
        self::assertStringContainsString('/C0', $pdf);
        self::assertStringContainsString('/C1', $pdf);
        self::assertStringContainsString('/N 1', $pdf);
        self::assertSame(2, $f->getFunctionType());
    }

    public function testFunctionType3(): void
    {
        $sub1 = new PdfReference(10);
        $sub2 = new PdfReference(11);
        $f = new FunctionType3(
            domain: new PdfArray([new PdfNumber(0), new PdfNumber(1)]),
            functions: new PdfArray([$sub1, $sub2]),
            bounds: new PdfArray([new PdfNumber(0.5)]),
            encode: new PdfArray([
                new PdfNumber(0), new PdfNumber(1),
                new PdfNumber(0), new PdfNumber(1),
            ])
        );
        $f->objectNumber = 1;
        $pdf = $f->toPdf();
        self::assertStringContainsString('/FunctionType 3', $pdf);
        self::assertStringContainsString('/Functions', $pdf);
        self::assertStringContainsString('/Bounds', $pdf);
        self::assertStringContainsString('/Encode', $pdf);
    }

    public function testFunctionType0AsStream(): void
    {
        $samples = "\x00\x80\xFF";
        $f = new FunctionType0(
            domain: new PdfArray([new PdfNumber(0), new PdfNumber(1)]),
            range: new PdfArray([new PdfNumber(0), new PdfNumber(1)]),
            size: new PdfArray([new PdfNumber(3)]),
            bitsPerSample: 8,
            samples: $samples,
        );
        $f->objectNumber = 1;
        $pdf = $f->toIndirectObject();
        self::assertStringContainsString('/FunctionType 0', $pdf);
        self::assertStringContainsString('/BitsPerSample 8', $pdf);
        self::assertStringContainsString('stream', $pdf);
        self::assertStringContainsString('endstream', $pdf);
    }

    public function testFunctionType4PostScript(): void
    {
        $f = new FunctionType4(
            domain: new PdfArray([new PdfNumber(0), new PdfNumber(1)]),
            range: new PdfArray([new PdfNumber(0), new PdfNumber(1)]),
            postScript: '{ 1 exch sub }',
        );
        $f->objectNumber = 1;
        $pdf = $f->toIndirectObject();
        self::assertStringContainsString('/FunctionType 4', $pdf);
        self::assertStringContainsString('1 exch sub', $pdf);
    }
}
