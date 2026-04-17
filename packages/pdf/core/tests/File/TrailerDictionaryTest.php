<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Tests\File;

use ApprLabs\Pdf\Core\File\TrailerDictionary;
use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfReference;
use ApprLabs\Pdf\Core\PdfString;
use PHPUnit\Framework\TestCase;

class TrailerDictionaryTest extends TestCase
{
    public function testBasicTrailer(): void
    {
        $t = new TrailerDictionary(new PdfReference(1));
        $t->size = 5;
        $t->info = new PdfReference(2);
        $t->id = new PdfArray([
            new PdfString(str_repeat("\x00", 16), hex: true),
            new PdfString(str_repeat("\xff", 16), hex: true),
        ]);
        $pdf = $t->toPdf();
        self::assertStringContainsString('/Size 5', $pdf);
        self::assertStringContainsString('/Root 1 0 R', $pdf);
        self::assertStringContainsString('/Info 2 0 R', $pdf);
        self::assertStringContainsString('/ID', $pdf);
    }

    public function testIncrementalTrailer(): void
    {
        $t = new TrailerDictionary(new PdfReference(1));
        $t->size = 99;
        $t->prev = 1234;
        $t->encrypt = new PdfReference(10);
        $pdf = $t->toPdf();
        self::assertStringContainsString('/Prev 1234', $pdf);
        self::assertStringContainsString('/Encrypt 10 0 R', $pdf);
    }
}
