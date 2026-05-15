<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Tests;

use Phpdftk\Pdf\Core\Filter\CCITTFaxDecodeParams;
use Phpdftk\Pdf\Core\Graphics\Function\FunctionType0;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfNumber;
use PHPUnit\Framework\TestCase;

class SmallPdfClassesTest extends TestCase
{
    public function testCcittFaxDecodeParamsEmpty(): void
    {
        $p = new CCITTFaxDecodeParams();
        $pdf = $p->toPdf();
        $this->assertStringContainsString('<<', $pdf);
        $this->assertStringNotContainsString('/K', $pdf);
    }

    public function testCcittFaxDecodeParamsAllFields(): void
    {
        $p = new CCITTFaxDecodeParams();
        $p->k = -1;
        $p->endOfLine = true;
        $p->encodedByteAlign = false;
        $p->columns = 1728;
        $p->rows = 100;
        $p->endOfBlock = false;
        $p->blackIs1 = true;
        $p->damagedRowsBeforeError = 5;

        $pdf = $p->toPdf();
        $this->assertStringContainsString('/K -1', $pdf);
        $this->assertStringContainsString('/EndOfLine true', $pdf);
        $this->assertStringContainsString('/EncodedByteAlign false', $pdf);
        $this->assertStringContainsString('/Columns 1728', $pdf);
        $this->assertStringContainsString('/Rows 100', $pdf);
        $this->assertStringContainsString('/EndOfBlock false', $pdf);
        $this->assertStringContainsString('/BlackIs1 true', $pdf);
        $this->assertStringContainsString('/DamagedRowsBeforeError 5', $pdf);
    }

    public function testFunctionType0Minimal(): void
    {
        $domain = new PdfArray([new PdfNumber(0), new PdfNumber(1)]);
        $range = new PdfArray([new PdfNumber(0), new PdfNumber(1)]);
        $size = new PdfArray([new PdfNumber(2)]);
        $f = new FunctionType0($domain, $range, $size, 8, "\x00\xFF");
        $f->objectNumber = 1;
        $this->assertSame(0, $f->getFunctionType());
        $pdf = $f->toIndirectObject();
        $this->assertStringContainsString('/FunctionType 0', $pdf);
        $this->assertStringContainsString('/Domain', $pdf);
        $this->assertStringContainsString('/Range', $pdf);
        $this->assertStringContainsString('/Size', $pdf);
        $this->assertStringContainsString('/BitsPerSample 8', $pdf);
    }

    public function testFunctionType0AllOptionalFields(): void
    {
        $domain = new PdfArray([new PdfNumber(0), new PdfNumber(1)]);
        $range = new PdfArray([new PdfNumber(0), new PdfNumber(1)]);
        $size = new PdfArray([new PdfNumber(4)]);
        $f = new FunctionType0($domain, $range, $size, 8, "\x00\x40\x80\xFF");
        $f->order = 3;
        $f->encode = new PdfArray([new PdfNumber(0), new PdfNumber(3)]);
        $f->decode = new PdfArray([new PdfNumber(0), new PdfNumber(255)]);
        $f->objectNumber = 1;
        $pdf = $f->toIndirectObject();
        $this->assertStringContainsString('/Order 3', $pdf);
        $this->assertStringContainsString('/Encode', $pdf);
        $this->assertStringContainsString('/Decode', $pdf);
    }
}
