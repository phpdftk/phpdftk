<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Tests\Filter;

use Phpdftk\Pdf\Core\Filter\CCITTFaxDecodeParams;
use Phpdftk\Pdf\Core\Filter\CryptFilterDecodeParams;
use Phpdftk\Pdf\Core\Filter\DCTDecodeParams;
use Phpdftk\Pdf\Core\Filter\FlateDecodeParams;
use Phpdftk\Pdf\Core\Filter\JBIG2DecodeParams;
use Phpdftk\Pdf\Core\Filter\JPXDecodeParams;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfReference;
use PHPUnit\Framework\TestCase;

class DecodeParamsTest extends TestCase
{
    public function testFlateDecodeParams(): void
    {
        $p = new FlateDecodeParams();
        $p->predictor = 15;
        $p->columns = 1024;
        $p->colors = 3;
        $p->bitsPerComponent = 8;
        $pdf = $p->toPdf();
        self::assertStringContainsString('/Predictor 15', $pdf);
        self::assertStringContainsString('/Columns 1024', $pdf);
        self::assertStringContainsString('/Colors 3', $pdf);
        self::assertStringContainsString('/BitsPerComponent 8', $pdf);
    }

    public function testCCITTFaxDecodeParams(): void
    {
        $p = new CCITTFaxDecodeParams();
        $p->k = -1;
        $p->columns = 2550;
        $p->rows = 3300;
        $p->blackIs1 = true;
        $pdf = $p->toPdf();
        self::assertStringContainsString('/K -1', $pdf);
        self::assertStringContainsString('/Columns 2550', $pdf);
        self::assertStringContainsString('/Rows 3300', $pdf);
        self::assertStringContainsString('/BlackIs1 true', $pdf);
    }

    public function testJBIG2DecodeParams(): void
    {
        $p = new JBIG2DecodeParams();
        $p->jbig2Globals = new PdfReference(5);
        self::assertStringContainsString('/JBIG2Globals 5 0 R', $p->toPdf());
    }

    public function testDCTDecodeParams(): void
    {
        $p = new DCTDecodeParams();
        $p->colorTransform = 1;
        self::assertStringContainsString('/ColorTransform 1', $p->toPdf());
    }

    public function testJPXDecodeParams(): void
    {
        $p = new JPXDecodeParams();
        $p->colorTransform = 0;
        $p->sMaskInData = 1;
        $pdf = $p->toPdf();
        self::assertStringContainsString('/ColorTransform 0', $pdf);
        self::assertStringContainsString('/SMaskInData 1', $pdf);
    }

    public function testCryptFilterDecodeParams(): void
    {
        $p = new CryptFilterDecodeParams();
        $p->type = new PdfName('CryptFilterDecodeParms');
        $p->name = new PdfName('Identity');
        $pdf = $p->toPdf();
        self::assertStringContainsString('/Type /CryptFilterDecodeParms', $pdf);
        self::assertStringContainsString('/Name /Identity', $pdf);
    }
}
