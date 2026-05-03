<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Tests\Font;

use PHPUnit\Framework\TestCase;
use Phpdftk\Pdf\Core\Font\CIDFont;
use Phpdftk\Pdf\Core\Font\CIDSystemInfo;

class CIDSystemInfoTest extends TestCase
{
    public function testRegistryField(): void
    {
        $info = new CIDSystemInfo('Adobe', 'Identity', 0);
        self::assertStringContainsString('/Registry (Adobe)', $info->toPdf());
    }

    public function testOrderingField(): void
    {
        $info = new CIDSystemInfo('Adobe', 'Identity', 0);
        self::assertStringContainsString('/Ordering (Identity)', $info->toPdf());
    }

    public function testSupplementField(): void
    {
        $info = new CIDSystemInfo('Adobe', 'Identity', 0);
        self::assertStringContainsString('/Supplement 0', $info->toPdf());
    }

    public function testSupplementNonZero(): void
    {
        $info = new CIDSystemInfo('Adobe', 'Japan1', 6);
        self::assertStringContainsString('/Supplement 6', $info->toPdf());
    }

    public function testAllFieldsPresent(): void
    {
        $info = new CIDSystemInfo('Adobe', 'CNS1', 4);
        $pdf = $info->toPdf();
        self::assertStringContainsString('/Registry', $pdf);
        self::assertStringContainsString('/Ordering', $pdf);
        self::assertStringContainsString('/Supplement', $pdf);
    }

    public function testUsedInCIDFont(): void
    {
        $info = new CIDSystemInfo('Adobe', 'Identity', 0);
        $font = new CIDFont('CIDFontType2', 'Arial', $info);
        $font->objectNumber = 1;
        $pdf = $font->toPdf();
        self::assertStringContainsString('/CIDSystemInfo', $pdf);
        self::assertStringContainsString('/Registry (Adobe)', $pdf);
        self::assertStringContainsString('/Ordering (Identity)', $pdf);
        self::assertStringContainsString('/Supplement 0', $pdf);
    }
}
