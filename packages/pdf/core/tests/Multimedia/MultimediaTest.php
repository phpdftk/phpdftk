<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Tests\Multimedia;

use ApprLabs\Pdf\Core\FileSpec\FileSpec;
use ApprLabs\Pdf\Core\Multimedia\MediaClipData;
use ApprLabs\Pdf\Core\Multimedia\MediaClipSection;
use ApprLabs\Pdf\Core\Multimedia\MediaPlayParams;
use ApprLabs\Pdf\Core\Multimedia\MediaRendition;
use ApprLabs\Pdf\Core\Multimedia\MediaScreenParams;
use ApprLabs\Pdf\Core\Multimedia\Movie;
use ApprLabs\Pdf\Core\Multimedia\Navigator;
use ApprLabs\Pdf\Core\Multimedia\SelectorRendition;
use ApprLabs\Pdf\Core\Multimedia\Sound;
use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\PdfReference;
use ApprLabs\Pdf\Core\PdfString;
use PHPUnit\Framework\TestCase;

class MultimediaTest extends TestCase
{
    public function testSound(): void
    {
        $s = new Sound(44100, "\x00\x01\x02");
        $s->objectNumber = 1;
        $s->c = 2;
        $s->b = 16;
        $s->e = new PdfName('Signed');
        $pdf = $s->toIndirectObject();
        self::assertStringContainsString('/Type /Sound', $pdf);
        self::assertStringContainsString('/R 44100', $pdf);
        self::assertStringContainsString('/C 2', $pdf);
        self::assertStringContainsString('/B 16', $pdf);
        self::assertStringContainsString('/E /Signed', $pdf);
        self::assertStringContainsString('stream', $pdf);
    }

    public function testMovie(): void
    {
        $spec = new FileSpec('movie.mov');
        $m = new Movie($spec);
        $m->objectNumber = 1;
        $m->aspect = new PdfArray([new PdfNumber(320), new PdfNumber(240)]);
        $m->rotate = 90;
        $m->poster = true;
        $pdf = $m->toPdf();
        self::assertStringContainsString('/F', $pdf);
        self::assertStringContainsString('/Aspect', $pdf);
        self::assertStringContainsString('/Rotate 90', $pdf);
        self::assertStringContainsString('/Poster true', $pdf);
    }

    public function testMediaRendition(): void
    {
        $r = new MediaRendition();
        $r->objectNumber = 1;
        $r->n = new PdfString('MyRendition');
        $r->c = new PdfReference(12);
        $r->p = new PdfReference(13);
        $pdf = $r->toPdf();
        self::assertStringContainsString('/Type /Rendition', $pdf);
        self::assertStringContainsString('/S /MR', $pdf);
        self::assertStringContainsString('/N (MyRendition)', $pdf);
        self::assertStringContainsString('/C 12 0 R', $pdf);
    }

    public function testSelectorRendition(): void
    {
        $r = new SelectorRendition();
        $r->objectNumber = 1;
        $r->r = new PdfArray([new PdfReference(20), new PdfReference(21)]);
        $pdf = $r->toPdf();
        self::assertStringContainsString('/S /SR', $pdf);
        self::assertStringContainsString('/R', $pdf);
        self::assertStringContainsString('20 0 R', $pdf);
    }

    public function testMediaClipData(): void
    {
        $spec = new FileSpec('clip.mp3');
        $c = new MediaClipData($spec);
        $c->objectNumber = 1;
        $c->ct = new PdfString('audio/mpeg');
        $pdf = $c->toPdf();
        self::assertStringContainsString('/Type /MediaClip', $pdf);
        self::assertStringContainsString('/S /MCD', $pdf);
        self::assertStringContainsString('/CT (audio/mpeg)', $pdf);
    }

    public function testMediaClipSection(): void
    {
        $parent = new PdfReference(30);
        $c = new MediaClipSection($parent);
        $c->objectNumber = 1;
        $pdf = $c->toPdf();
        self::assertStringContainsString('/S /MCS', $pdf);
        self::assertStringContainsString('/D 30 0 R', $pdf);
    }

    public function testMediaPlayParams(): void
    {
        $p = new MediaPlayParams();
        $p->objectNumber = 1;
        $p->mh = new PdfDictionary(['V' => new PdfNumber(100)]);
        $pdf = $p->toPdf();
        self::assertStringContainsString('/Type /MediaPlayParams', $pdf);
        self::assertStringContainsString('/MH', $pdf);
        self::assertStringContainsString('/V 100', $pdf);
    }

    public function testMediaScreenParams(): void
    {
        $p = new MediaScreenParams();
        $p->objectNumber = 1;
        $p->be = new PdfDictionary(['W' => new PdfNumber(3)]);
        $pdf = $p->toPdf();
        self::assertStringContainsString('/Type /MediaScreenParams', $pdf);
        self::assertStringContainsString('/BE', $pdf);
    }

    public function testNavigator(): void
    {
        $n = new Navigator();
        $n->objectNumber = 1;
        $n->nr = new PdfString('main');
        $pdf = $n->toPdf();
        self::assertStringContainsString('/Type /Navigator', $pdf);
        self::assertStringContainsString('/NR (main)', $pdf);
    }
}
