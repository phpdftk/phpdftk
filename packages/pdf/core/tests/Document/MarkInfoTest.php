<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Tests\Document;

use PHPUnit\Framework\TestCase;
use ApprLabs\Pdf\Core\Document\Catalog;
use ApprLabs\Pdf\Core\Document\MarkInfo;

class MarkInfoTest extends TestCase
{
    public function testEmptyMarkInfoProducesEmptyDict(): void
    {
        $mi = new MarkInfo();
        $pdf = $mi->toPdf();
        self::assertStringContainsString('<<', $pdf);
        self::assertStringNotContainsString('/Marked', $pdf);
        self::assertStringNotContainsString('/UserProperties', $pdf);
        self::assertStringNotContainsString('/Suspects', $pdf);
    }

    public function testMarkedTrue(): void
    {
        $mi = new MarkInfo();
        $mi->marked = true;
        self::assertStringContainsString('/Marked true', $mi->toPdf());
    }

    public function testMarkedFalse(): void
    {
        $mi = new MarkInfo();
        $mi->marked = false;
        self::assertStringContainsString('/Marked false', $mi->toPdf());
    }

    public function testUserProperties(): void
    {
        $mi = new MarkInfo();
        $mi->userProperties = true;
        self::assertStringContainsString('/UserProperties true', $mi->toPdf());
    }

    public function testSuspects(): void
    {
        $mi = new MarkInfo();
        $mi->suspects = false;
        self::assertStringContainsString('/Suspects false', $mi->toPdf());
    }

    public function testAllFieldsSet(): void
    {
        $mi = new MarkInfo();
        $mi->marked = true;
        $mi->userProperties = true;
        $mi->suspects = false;
        $pdf = $mi->toPdf();
        self::assertStringContainsString('/Marked true', $pdf);
        self::assertStringContainsString('/UserProperties true', $pdf);
        self::assertStringContainsString('/Suspects false', $pdf);
    }

    public function testAssignedToCatalog(): void
    {
        $mi = new MarkInfo();
        $mi->marked = true;

        $catalog = new Catalog();
        $catalog->objectNumber = 1;
        $catalog->markInfo = $mi;

        $pdf = $catalog->toPdf();
        self::assertStringContainsString('/MarkInfo', $pdf);
        self::assertStringContainsString('/Marked true', $pdf);
    }
}
