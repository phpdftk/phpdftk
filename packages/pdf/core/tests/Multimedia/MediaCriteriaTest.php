<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Tests\Multimedia;

use ApprLabs\Pdf\Core\Multimedia\MediaCriteria;
use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfString;
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
