<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Tests\Multimedia;

use Phpdftk\Pdf\Core\Multimedia\MediaPlayParams;
use Phpdftk\Pdf\Core\Multimedia\Navigator;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfString;
use PHPUnit\Framework\TestCase;

class MediaPlayParamsAndNavigatorTest extends TestCase
{
    public function testMediaPlayParamsMinimal(): void
    {
        $p = new MediaPlayParams();
        $pdf = $p->toPdf();
        $this->assertStringContainsString('/Type /MediaPlayParams', $pdf);
    }

    public function testMediaPlayParamsAllFields(): void
    {
        $p = new MediaPlayParams();
        $p->mh = new PdfDictionary();
        $p->be = new PdfDictionary();
        $p->pl = new PdfDictionary();
        $pdf = $p->toPdf();
        $this->assertStringContainsString('/MH', $pdf);
        $this->assertStringContainsString('/BE', $pdf);
        $this->assertStringContainsString('/PL', $pdf);
    }

    public function testNavigatorMinimal(): void
    {
        $n = new Navigator();
        $pdf = $n->toPdf();
        $this->assertStringContainsString('/Type /Navigator', $pdf);
    }

    public function testNavigatorAllFields(): void
    {
        $n = new Navigator();
        $n->na = new PdfDictionary();
        $n->nr = new PdfString('My nav');
        $n->du = new PdfDictionary();
        $pdf = $n->toPdf();
        $this->assertStringContainsString('/NA', $pdf);
        $this->assertStringContainsString('/NR', $pdf);
        $this->assertStringContainsString('/Duration', $pdf);
    }
}
