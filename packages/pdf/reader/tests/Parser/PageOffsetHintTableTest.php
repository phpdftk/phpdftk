<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Reader\Tests\Parser;

use Phpdftk\Pdf\Reader\Parser\PageHintEntry;
use Phpdftk\Pdf\Reader\Parser\PageOffsetHintTable;
use PHPUnit\Framework\TestCase;

class PageOffsetHintTableTest extends TestCase
{
    private function entry(int $lenDelta = 0): PageHintEntry
    {
        return new PageHintEntry(0, $lenDelta, 0, [], 0, 0, 0);
    }

    public function testGetByteRangeForFirstPage(): void
    {
        $table = new PageOffsetHintTable(
            minObjectsPerPage: 5,
            firstPageLocation: 1000,
            minPageLength: 100,
            minSharedRefsPerPage: 0,
            minSharedObjId: 0,
            minContentStreamOffset: 0,
            minContentStreamLength: 0,
            entries: [$this->entry(50), $this->entry(20), $this->entry(30)],
        );

        $range = $table->getPageByteRange(0);
        $this->assertSame(1000, $range['offset']);
        $this->assertSame(150, $range['length']); // 100 + 50
    }

    public function testGetByteRangeForLaterPagesIsCumulative(): void
    {
        $table = new PageOffsetHintTable(
            5,
            1000,
            100,
            0,
            0,
            0,
            0,
            [$this->entry(50), $this->entry(20), $this->entry(30)],
        );

        // Page index 1: offset = page 1's offset = pageLengthDelta from index 1's range...
        // Actually the implementation accumulates from entries[1..pageIndex-1] starting at offset 0.
        $range1 = $table->getPageByteRange(1);
        $this->assertSame(0, $range1['offset']);
        $this->assertSame(120, $range1['length']); // 100 + 20

        $range2 = $table->getPageByteRange(2);
        $this->assertSame(120, $range2['offset']); // sum of length for entry 1 = 120
        $this->assertSame(130, $range2['length']); // 100 + 30
    }

    public function testOutOfRangeBelowThrows(): void
    {
        $table = new PageOffsetHintTable(5, 1000, 100, 0, 0, 0, 0, [$this->entry(0)]);
        $this->expectException(\OutOfRangeException::class);
        $table->getPageByteRange(-1);
    }

    public function testOutOfRangeAboveThrows(): void
    {
        $table = new PageOffsetHintTable(5, 1000, 100, 0, 0, 0, 0, [$this->entry(0)]);
        $this->expectException(\OutOfRangeException::class);
        $table->getPageByteRange(5);
    }
}
