<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Reader\Tests\Parser;

use Phpdftk\Pdf\Reader\Parser\HintTableParser;
use Phpdftk\Pdf\Reader\Parser\PageOffsetHintTable;
use PHPUnit\Framework\TestCase;

class HintTableParserTest extends TestCase
{
    /**
     * Build a minimal page offset hint table header (44 bytes)
     * with all fields set to simple values, followed by trivial
     * per-page bit-packed entries.
     */
    public function testParsePageOffsetTableHeader(): void
    {
        // Build header: 11 x 4 bytes = 44 bytes
        $header = '';
        $header .= pack('N', 3);   // Item 1: minObjectsPerPage = 3
        $header .= pack('N', 100); // Item 2: firstPageLocation = 100
        $header .= pack('N', 0);   // Item 3: bitsForObjectCount = 0 (all pages same)
        $header .= pack('N', 500); // Item 4: minPageLength = 500
        $header .= pack('N', 0);   // Item 5: bitsForPageLength = 0
        $header .= pack('N', 0);   // Item 6: minSharedRefsPerPage = 0
        $header .= pack('N', 0);   // Item 7: bitsForSharedRefCount = 0
        $header .= pack('N', 1);   // Item 8: minSharedObjId = 1
        $header .= pack('N', 0);   // Item 9: bitsForSharedObjId = 0
        $header .= pack('N', 0);   // Item 10: bitsForSharedObjNum = 0
        $header .= pack('N', 200); // Item 11: minContentStreamOffset = 200

        // No per-page entries needed since all bit widths are 0
        // (all deltas are implicitly 0)

        $parser = new HintTableParser($header);
        $table = $parser->parsePageOffsetTable(0, 3);

        $this->assertSame(3, $table->minObjectsPerPage);
        $this->assertSame(100, $table->firstPageLocation);
        $this->assertSame(500, $table->minPageLength);
        $this->assertCount(3, $table->entries);

        // All deltas should be 0
        foreach ($table->entries as $entry) {
            $this->assertSame(0, $entry->objectCountDelta);
            $this->assertSame(0, $entry->pageLengthDelta);
        }
    }

    public function testParsePageOffsetTableWithDeltas(): void
    {
        // Build header with 2-bit page length deltas
        $header = '';
        $header .= pack('N', 2);   // minObjectsPerPage
        $header .= pack('N', 50);  // firstPageLocation
        $header .= pack('N', 0);   // bitsForObjectCount = 0
        $header .= pack('N', 300); // minPageLength
        $header .= pack('N', 2);   // bitsForPageLength = 2 (values 0-3)
        $header .= pack('N', 0);   // minSharedRefsPerPage
        $header .= pack('N', 0);   // bitsForSharedRefCount
        $header .= pack('N', 0);   // minSharedObjId
        $header .= pack('N', 0);   // bitsForSharedObjId
        $header .= pack('N', 0);   // bitsForSharedObjNum
        $header .= pack('N', 0);   // minContentStreamOffset

        // Per-page bit-packed data for 2 pages:
        // Object count deltas (0 bits each): nothing
        // Page length deltas (2 bits each): page0=1 (01), page1=3 (11)
        // → 0b01110000 = 0x70 (padded to byte)
        $bitData = "\x70";

        $parser = new HintTableParser($header . $bitData);
        $table = $parser->parsePageOffsetTable(0, 2);

        $this->assertCount(2, $table->entries);
        $this->assertSame(1, $table->entries[0]->pageLengthDelta);
        $this->assertSame(3, $table->entries[1]->pageLengthDelta);
    }

    public function testGetPageByteRangeFirstPage(): void
    {
        // Build a simple table with known values
        $header = '';
        $header .= pack('N', 2);   // minObjectsPerPage
        $header .= pack('N', 100); // firstPageLocation
        $header .= pack('N', 0);   // bitsForObjectCount
        $header .= pack('N', 400); // minPageLength
        $header .= pack('N', 0);   // bitsForPageLength
        $header .= pack('N', 0);   // minSharedRefsPerPage
        $header .= pack('N', 0);   // bitsForSharedRefCount
        $header .= pack('N', 0);   // minSharedObjId
        $header .= pack('N', 0);   // bitsForSharedObjId
        $header .= pack('N', 0);   // bitsForSharedObjNum
        $header .= pack('N', 0);   // minContentStreamOffset

        $parser = new HintTableParser($header);
        $table = $parser->parsePageOffsetTable(0, 2);

        $range = $table->getPageByteRange(0);
        $this->assertSame(100, $range['offset']);
        $this->assertSame(400, $range['length']);
    }

    public function testGetPageByteRangeOutOfBoundsThrows(): void
    {
        $header = '';
        for ($i = 0; $i < 11; $i++) {
            $header .= pack('N', 0);
        }

        $parser = new HintTableParser($header);
        $table = $parser->parsePageOffsetTable(0, 1);

        $this->expectException(\OutOfRangeException::class);
        $table->getPageByteRange(5);
    }

    public function testTooShortDataThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        $parser = new HintTableParser('short');
        $parser->parsePageOffsetTable(0, 1);
    }

    public function testParseSharedObjectTable(): void
    {
        // Build header: 5 x 4 bytes = 20 bytes
        $header = '';
        $header .= pack('N', 10);  // firstSharedObjNumber
        $header .= pack('N', 200); // firstSharedObjOffset
        $header .= pack('N', 2);   // numSharedGroups = 2
        $header .= pack('N', 50);  // minGroupLength
        $header .= pack('N', 0);   // bitsForGroupLength = 0

        // Per-group data: all zeros since bit widths are 0
        // Signature flags: 2 bits → 0b00... = 0x00
        $bitData = "\x00";

        $parser = new HintTableParser($header . $bitData);
        $table = $parser->parseSharedObjectTable(0);

        $this->assertSame(10, $table->firstSharedObjNumber);
        $this->assertSame(200, $table->firstSharedObjOffset);
        $this->assertSame(2, $table->numSharedGroups);
        $this->assertCount(2, $table->entries);
    }
}
