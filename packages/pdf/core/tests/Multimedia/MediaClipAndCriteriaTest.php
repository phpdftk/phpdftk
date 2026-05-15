<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Tests\Multimedia;

use Phpdftk\Pdf\Core\FileSpec\FileSpec;
use Phpdftk\Pdf\Core\Multimedia\MediaClipData;
use Phpdftk\Pdf\Core\Multimedia\MediaClipSection;
use Phpdftk\Pdf\Core\Multimedia\MediaCriteria;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfString;
use PHPUnit\Framework\TestCase;

class MediaClipAndCriteriaTest extends TestCase
{
    public function testMediaCriteriaEmpty(): void
    {
        $c = new MediaCriteria();
        $pdf = $c->toPdf();
        $this->assertStringContainsString('/Type /MediaCriteria', $pdf);
    }

    public function testMediaCriteriaAllFields(): void
    {
        $c = new MediaCriteria();
        $c->a = true;
        $c->c = false;
        $c->o = true;
        $c->s = false;
        $c->r = 256;
        $c->d = new PdfDictionary();
        $c->z = new PdfDictionary();
        $c->v = new PdfArray([new PdfNumber(2)]);
        $c->p = new PdfArray([new PdfName('TempAlways')]);
        $c->l = new PdfArray([new PdfString('en')]);

        $pdf = $c->toPdf();
        foreach (['/A true', '/C false', '/O true', '/S false', '/R 256', '/D', '/Z', '/V', '/P', '/L'] as $needle) {
            $this->assertStringContainsString($needle, $pdf);
        }
    }

    public function testMediaClipDataMinimalAndFull(): void
    {
        $clip = new MediaClipData(new PdfReference(5));
        $this->assertSame('MCD', $clip->getMediaClipSubtype());
        $pdf = $clip->toPdf();
        $this->assertStringContainsString('/Type /MediaClip', $pdf);
        $this->assertStringContainsString('/S /MCD', $pdf);
        $this->assertStringContainsString('/D 5 0 R', $pdf);

        $clip2 = new MediaClipData(new FileSpec('movie.mp4'));
        $clip2->ct = new PdfString('video/mp4');
        $clip2->p = new PdfDictionary();
        $clip2->alt = new PdfString('Alternate text');
        $clip2->pl = new PdfDictionary();
        $clip2->bu = new PdfDictionary();
        $pdf2 = $clip2->toPdf();
        $this->assertStringContainsString('/CT', $pdf2);
        $this->assertStringContainsString('/P', $pdf2);
        $this->assertStringContainsString('/Alt', $pdf2);
        $this->assertStringContainsString('/PL', $pdf2);
        $this->assertStringContainsString('/BU', $pdf2);
    }

    public function testMediaClipSectionMinimalAndFull(): void
    {
        $section = new MediaClipSection(new PdfReference(10));
        $this->assertSame('MCS', $section->getMediaClipSubtype());
        $pdf = $section->toPdf();
        $this->assertStringContainsString('/S /MCS', $pdf);
        $this->assertStringContainsString('/D 10 0 R', $pdf);

        $section2 = new MediaClipSection(new PdfReference(11));
        $section2->alt = new PdfString('Alt');
        $section2->mh = new PdfDictionary();
        $section2->be = new PdfDictionary();

        $pdf2 = $section2->toPdf();
        $this->assertStringContainsString('/Alt', $pdf2);
        $this->assertStringContainsString('/MH', $pdf2);
        $this->assertStringContainsString('/BE', $pdf2);
    }
}
