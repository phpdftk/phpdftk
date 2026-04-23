<?php

declare(strict_types=1);

namespace ApprLabs\Filters\Tests;

use ApprLabs\Filters\Jbig2Filter;
use PHPUnit\Framework\TestCase;

class Jbig2FilterTest extends TestCase
{
    public function testDecodeEmptyData(): void
    {
        $filter = new Jbig2Filter();
        $this->assertSame('', $filter->decode(''));
    }

    public function testEncodeThrows(): void
    {
        $filter = new Jbig2Filter();
        $this->expectException(\RuntimeException::class);
        $filter->encode('test');
    }

    public function testConstructorAcceptsGlobals(): void
    {
        $filter = new Jbig2Filter(globals: 'some-global-data');
        $this->assertInstanceOf(Jbig2Filter::class, $filter);
    }

    public function testDecodeInvalidDataReturnsFallback(): void
    {
        // Invalid JBIG2 data should fall back gracefully
        $filter = new Jbig2Filter();
        $data = str_repeat("\xFF", 20);
        $result = $filter->decode($data);

        // Should return something (either decoded or raw pass-through)
        $this->assertIsString($result);
    }

    public function testDecodeFileFormatHeader(): void
    {
        // Build a minimal JBIG2 file with just a file header + end-of-file segment
        $jbig2 = "\x97\x4A\x42\x32\x0D\x0A\x1A\x0A"; // 8-byte signature
        $jbig2 .= "\x01"; // flags: sequential, known page count
        $jbig2 .= pack('N', 1); // 1 page

        // End-of-file segment (type 51)
        $jbig2 .= pack('N', 1);       // segment number
        $jbig2 .= chr(51);            // flags: type 51 (end of file)
        $jbig2 .= chr(0);             // referred count = 0
        $jbig2 .= chr(0);             // page association = 0
        $jbig2 .= pack('N', 0);       // data length = 0

        $filter = new Jbig2Filter();
        $result = $filter->decode($jbig2);

        // With no page info or generic region, returns raw data or empty
        $this->assertIsString($result);
    }

    public function testDecodeWithPageInfoSegment(): void
    {
        // Build JBIG2 with page info segment
        // No file header (PDF embedded format)

        // Page info segment (type 48)
        $pageInfo = pack('N', 8);      // width = 8 pixels
        $pageInfo .= pack('N', 1);     // height = 1 pixel
        $pageInfo .= pack('N', 0);     // x resolution
        $pageInfo .= pack('N', 0);     // y resolution
        $pageInfo .= chr(0);           // flags
        $pageInfo .= pack('n', 0);     // striping info

        $segment = pack('N', 0);       // segment number 0
        $segment .= chr(48);           // type 48 = page info
        $segment .= chr(0);            // referred count = 0
        $segment .= chr(1);            // page association = 1
        $segment .= pack('N', strlen($pageInfo)); // data length

        // End-of-page segment (type 49)
        $eop = pack('N', 1);           // segment number 1
        $eop .= chr(49);               // type 49 = end of page
        $eop .= chr(0);                // referred count = 0
        $eop .= chr(1);                // page association = 1
        $eop .= pack('N', 0);          // data length = 0

        $data = $segment . $pageInfo . $eop;

        $filter = new Jbig2Filter();
        $result = $filter->decode($data);

        // Without a generic region, should return raw or fallback
        $this->assertIsString($result);
    }

    public function testDecodeWithGlobals(): void
    {
        // Globals are prepended to the data before parsing
        $globals = str_repeat("\x00", 10);
        $data = str_repeat("\xFF", 10);

        $filter = new Jbig2Filter(globals: $globals);
        $result = $filter->decode($data);

        // Should not crash
        $this->assertIsString($result);
    }
}
