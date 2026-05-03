<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Tests\Multimedia;

use Phpdftk\Pdf\Core\Multimedia\MediaCriteria;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfString;
use PHPUnit\Framework\TestCase;

class MediaCriteriaTest extends TestCase
{
    public function testFields(): void
    {
        $c = new MediaCriteria();
        $c->objectNumber = 1;
        $c->a = true;
        $c->s = false;
        $c->r = 256;
        $c->l = new PdfArray([new PdfString('en-US'), new PdfString('fr-FR')]);
        $pdf = $c->toPdf();
        self::assertStringContainsString('/Type /MediaCriteria', $pdf);
        self::assertStringContainsString('/A true', $pdf);
        self::assertStringContainsString('/S false', $pdf);
        self::assertStringContainsString('/R 256', $pdf);
        self::assertStringContainsString('/L', $pdf);
    }
}
