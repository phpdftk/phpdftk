<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Tests\Document;

use Phpdftk\Pdf\Core\Document\BoxColorInfo;
use Phpdftk\Pdf\Core\Document\BoxStyle;
use Phpdftk\Pdf\Core\Document\Catalog;
use Phpdftk\Pdf\Core\Document\DSS;
use Phpdftk\Pdf\Core\Document\Page;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfReference;
use PHPUnit\Framework\TestCase;

class CatalogPhaseATest extends TestCase
{
    public function testCatalogNewPDF2Fields(): void
    {
        $cat = new Catalog();
        $cat->objectNumber = 1;
        $dss = new DSS();
        $dss->objectNumber = 2;
        $cat->dss = $dss;
        $cat->extensions = new PdfDictionary(['ADBE' => new PdfName('1.7')]);
        $cat->af = new PdfArray([new PdfReference(7)]);
        $cat->dPartRoot = new PdfReference(8);
        $pdf = $cat->toPdf();
        self::assertStringContainsString('/DSS', $pdf);
        self::assertStringContainsString('/Extensions', $pdf);
        self::assertStringContainsString('/AF', $pdf);
        self::assertStringContainsString('/DPartRoot 8 0 R', $pdf);
    }

    public function testDSS(): void
    {
        $dss = new DSS();
        $dss->objectNumber = 1;
        $dss->certs = new PdfArray([new PdfReference(10)]);
        $dss->ocsps = new PdfArray([new PdfReference(11)]);
        $dss->crls = new PdfArray([new PdfReference(12)]);
        $pdf = $dss->toPdf();
        self::assertStringContainsString('/Certs', $pdf);
        self::assertStringContainsString('/OCSPs', $pdf);
        self::assertStringContainsString('/CRLs', $pdf);
    }

    public function testBoxColorInfoAndBoxStyle(): void
    {
        $style = new BoxStyle();
        $style->c = new PdfArray([new PdfNumber(1), new PdfNumber(0), new PdfNumber(0)]);
        $style->w = 0.5;
        $style->s = new PdfName('D');
        $style->d = new PdfArray([new PdfNumber(3), new PdfNumber(2)]);

        $bci = new BoxColorInfo();
        $bci->cropBox = $style;
        $bci->trimBox = $style;

        $page = new Page();
        $page->objectNumber = 1;
        $page->mediaBox = new PdfArray([
            new PdfNumber(0), new PdfNumber(0),
            new PdfNumber(612), new PdfNumber(792),
        ]);
        $page->boxColorInfo = $bci;
        $pdf = $page->toPdf();
        self::assertStringContainsString('/BoxColorInfo', $pdf);
        self::assertStringContainsString('/CropBox', $pdf);
        self::assertStringContainsString('/W 0.5', $pdf);
    }

    public function testPagePDF2Fields(): void
    {
        $page = new Page();
        $page->objectNumber = 1;
        $page->mediaBox = new PdfArray([
            new PdfNumber(0), new PdfNumber(0),
            new PdfNumber(612), new PdfNumber(792),
        ]);
        $page->af = new PdfArray([new PdfReference(5)]);
        $page->outputIntents = new PdfArray([new PdfReference(6)]);
        $page->dPart = new PdfReference(7);
        $pdf = $page->toPdf();
        self::assertStringContainsString('/AF', $pdf);
        self::assertStringContainsString('/OutputIntents', $pdf);
        self::assertStringContainsString('/DPart 7 0 R', $pdf);
    }
}
