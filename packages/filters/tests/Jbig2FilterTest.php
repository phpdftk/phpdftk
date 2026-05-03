<?php

declare(strict_types=1);

namespace Phpdftk\Filters\Tests;

use Phpdftk\Filters\Jbig2Filter;
use PHPUnit\Framework\TestCase;

class Jbig2FilterTest extends TestCase
{
    // -----------------------------------------------------------------------
    // Decode-only tests (preserved)
    // -----------------------------------------------------------------------

    public function testDecodeEmptyData(): void
    {
        $filter = new Jbig2Filter();
        $this->assertSame('', $filter->decode(''));
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

    // -----------------------------------------------------------------------
    // Encode empty
    // -----------------------------------------------------------------------

    public function testEncodeEmptyData(): void
    {
        $filter = new Jbig2Filter(width: 8, height: 1);
        $this->assertSame('', $filter->encode(''));
    }

    // -----------------------------------------------------------------------
    // Encode requires dimensions
    // -----------------------------------------------------------------------

    public function testEncodeRequiresDimensions(): void
    {
        $filter = new Jbig2Filter();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('width and height');
        $filter->encode("\xFF");
    }

    public function testEncodeRequiresWidth(): void
    {
        $filter = new Jbig2Filter(width: 0, height: 1);
        $this->expectException(\RuntimeException::class);
        $filter->encode("\xFF");
    }

    public function testEncodeRequiresHeight(): void
    {
        $filter = new Jbig2Filter(width: 8, height: 0);
        $this->expectException(\RuntimeException::class);
        $filter->encode("\xFF");
    }

    // -----------------------------------------------------------------------
    // Encode/decode roundtrip
    // -----------------------------------------------------------------------

    public function testEncodeDecodeAllWhite(): void
    {
        // 8x1 all-white bitmap (blackIs1=true in JBIG2: 0=white)
        $raw = chr(0x00);
        $filter = new Jbig2Filter(width: 8, height: 1);
        $this->assertSame($raw, $filter->decode($filter->encode($raw)));
    }

    public function testEncodeDecodeAllBlack(): void
    {
        // 8x1 all-black bitmap (blackIs1=true: 1=black)
        $raw = chr(0xFF);
        $filter = new Jbig2Filter(width: 8, height: 1);
        $this->assertSame($raw, $filter->decode($filter->encode($raw)));
    }

    public function testEncodeDecodeMixedRow(): void
    {
        // 4 white + 4 black
        $raw = chr(0x0F);
        $filter = new Jbig2Filter(width: 8, height: 1);
        $this->assertSame($raw, $filter->decode($filter->encode($raw)));
    }

    public function testEncodeDecodeMultipleRows(): void
    {
        // 8x4 bitmap
        $raw = chr(0xFF) . chr(0x00) . chr(0xF0) . chr(0x0F);
        $filter = new Jbig2Filter(width: 8, height: 4);
        $this->assertSame($raw, $filter->decode($filter->encode($raw)));
    }

    public function testEncodeDecodeWideImage(): void
    {
        // 32x4 bitmap (4 bytes per row)
        $raw = '';
        for ($r = 0; $r < 4; $r++) {
            for ($c = 0; $c < 4; $c++) {
                $raw .= chr(($r * 4 + $c) * 17 % 256);
            }
        }
        $filter = new Jbig2Filter(width: 32, height: 4);
        $this->assertSame($raw, $filter->decode($filter->encode($raw)));
    }

    public function testEncodeDecodeRandomBitmap(): void
    {
        // 16x5 random bitmap
        $raw = '';
        for ($i = 0; $i < 10; $i++) {
            $raw .= chr(($i * 37 + 13) % 256);
        }
        $filter = new Jbig2Filter(width: 16, height: 5);
        $this->assertSame($raw, $filter->decode($filter->encode($raw)));
    }

    // -----------------------------------------------------------------------
    // Segment structure
    // -----------------------------------------------------------------------

    public function testEncodeProducesValidSegments(): void
    {
        $raw = chr(0xFF);
        $filter = new Jbig2Filter(width: 8, height: 1);
        $encoded = $filter->encode($raw);

        // Should contain at least: page info (48) + generic region (39) + end of page (49)
        // Segment headers have type byte at offset 4 from segment start
        $this->assertGreaterThan(30, strlen($encoded));

        // First segment should be page info (type 48)
        $type0 = ord($encoded[4]);
        $this->assertSame(48, $type0);

        // Find the end-of-page segment (type 49) somewhere in the data
        $found49 = false;
        for ($i = 10; $i < strlen($encoded) - 5; $i++) {
            // Look for segment headers: 4-byte number, then type byte
            if (ord($encoded[$i + 4]) === 49) {
                $found49 = true;
                break;
            }
        }
        $this->assertTrue($found49, 'End-of-page segment not found');
    }

    // -----------------------------------------------------------------------
    // Compression check
    // -----------------------------------------------------------------------

    public function testEncodeCompressesBetter(): void
    {
        // 100 all-white rows of 8 pixels
        $raw = str_repeat(chr(0x00), 100);
        $filter = new Jbig2Filter(width: 8, height: 100);
        $encoded = $filter->encode($raw);

        // Despite JBIG2 segment overhead, repetitive data should compress well
        // The segment overhead is ~50 bytes, so for 100 bytes of raw data
        // the encoded form should still be smaller
        $this->assertLessThan(strlen($raw), strlen($encoded));
    }

    // -----------------------------------------------------------------------
    // Constructor backward compatibility
    // -----------------------------------------------------------------------

    public function testConstructorBackwardCompatible(): void
    {
        // No width/height — still works for decoding
        $filter1 = new Jbig2Filter();
        $this->assertInstanceOf(Jbig2Filter::class, $filter1);

        $filter2 = new Jbig2Filter(globals: 'x');
        $this->assertInstanceOf(Jbig2Filter::class, $filter2);
    }
}
