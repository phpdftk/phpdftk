<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Tests\Font;

use Phpdftk\Pdf\Core\Font\CIDSystemInfo;
use Phpdftk\Pdf\Core\Font\CMapStream;
use Phpdftk\Pdf\Core\Font\FontFile\CFFFontFile;
use Phpdftk\Pdf\Core\Font\FontFile\TrueTypeFontFile;
use Phpdftk\Pdf\Core\Font\FontFile\Type1FontFile;
use Phpdftk\Pdf\Core\PdfName;
use PHPUnit\Framework\TestCase;

class CMapAndFontFileTest extends TestCase
{
    public function testCMapStream(): void
    {
        $cmap = new CMapStream('/CIDInit /ProcSet findresource begin');
        $cmap->objectNumber = 1;
        $cmap->cMapName = new PdfName('Adobe-Identity-UCS');
        $cmap->cidSystemInfo = new CIDSystemInfo('Adobe', 'UCS', 0);
        $cmap->wMode = 0;
        $pdf = $cmap->toIndirectObject();
        self::assertStringContainsString('/Type /CMap', $pdf);
        self::assertStringContainsString('/CMapName /Adobe-Identity-UCS', $pdf);
        self::assertStringContainsString('/WMode 0', $pdf);
        self::assertStringContainsString('/CIDSystemInfo', $pdf);
    }

    public function testType1FontFile(): void
    {
        $ff = new Type1FontFile('fake-type1-bytes', 100, 200, 50);
        $ff->objectNumber = 1;
        $pdf = $ff->toIndirectObject();
        self::assertStringContainsString('/Length1 100', $pdf);
        self::assertStringContainsString('/Length2 200', $pdf);
        self::assertStringContainsString('/Length3 50', $pdf);
        self::assertStringContainsString('stream', $pdf);
    }

    public function testTrueTypeFontFile(): void
    {
        $ff = new TrueTypeFontFile('fake-ttf-bytes');
        $ff->objectNumber = 1;
        $pdf = $ff->toIndirectObject();
        self::assertStringContainsString('/Length1 14', $pdf);  // strlen('fake-ttf-bytes')
    }

    public function testCFFFontFile(): void
    {
        $ff = new CFFFontFile('fake-cff-bytes', 'OpenType');
        $ff->objectNumber = 1;
        $pdf = $ff->toIndirectObject();
        self::assertStringContainsString('/Subtype /OpenType', $pdf);
    }

    public function testCFFFontFileDefaultSubtype(): void
    {
        $ff = new CFFFontFile('fake-cff-bytes');
        $ff->objectNumber = 1;
        self::assertStringContainsString('/Subtype /Type1C', $ff->toIndirectObject());
    }
}
