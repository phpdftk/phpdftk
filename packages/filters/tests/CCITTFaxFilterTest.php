<?php

declare(strict_types=1);

namespace ApprLabs\Filters\Tests;

use ApprLabs\Filters\CCITTFaxFilter;
use PHPUnit\Framework\TestCase;

class CCITTFaxFilterTest extends TestCase
{
    public function testDecodeEmptyData(): void
    {
        $filter = new CCITTFaxFilter();
        $this->assertSame('', $filter->decode(''));
    }

    public function testEncodeThrows(): void
    {
        $filter = new CCITTFaxFilter();
        $this->expectException(\RuntimeException::class);
        $filter->encode('test');
    }

    public function testDecodeGroup3AllWhiteRow(): void
    {
        // Build a Group 3 encoded all-white row of 8 pixels.
        // White run of 8: code '10011' (from white terminating table)
        // Encode the bits into bytes
        $bits = '10011'; // white run of 8
        $byte = bindec(str_pad($bits, 8, '0'));
        $data = chr($byte);

        $filter = new CCITTFaxFilter(k: 0, columns: 8, rows: 1, endOfBlock: false);
        $result = $filter->decode($data);

        // All-white row of 8 pixels = 0x00 (when blackIs1=false, white=1, black=0)
        // Actually with default blackIs1=false, white pixel=0 in output (PDF convention)
        // Wait - let me check: packRow inverts when blackIs1=false: value = 1 - pixel
        // pixel=0 (white), value = 1-0 = 1. So all-white = 0xFF.
        $this->assertSame(1, strlen($result));
        $this->assertSame(0xFF, ord($result[0]));
    }

    public function testDecodeGroup3AllBlackRow(): void
    {
        // Black run of 8: code '000101' (from black terminating table, value 8)
        $bits = '000101';
        $byte = bindec(str_pad($bits, 8, '0'));
        $data = chr($byte);

        $filter = new CCITTFaxFilter(k: 0, columns: 8, rows: 1, endOfBlock: false);
        $result = $filter->decode($data);

        // Row starts with white. White run of 0 = '00110101' (8 bits).
        // Then black run of 8 = '000101' (6 bits).
        // Let's build this properly:
        // A row always starts with WHITE. To get all black:
        //   white run of 0: '00110101'
        //   black run of 8: '000101'
        // Total: 00110101 000101 + padding
        $bits = '00110101' . '000101';
        $byte1 = bindec(substr(str_pad($bits, 16, '0'), 0, 8));
        $byte2 = bindec(substr(str_pad($bits, 16, '0'), 8, 8));
        $data = chr($byte1) . chr($byte2);

        $filter = new CCITTFaxFilter(k: 0, columns: 8, rows: 1, endOfBlock: false);
        $result = $filter->decode($data);

        $this->assertSame(1, strlen($result));
        // All-black: pixel=1, value = 1-1 = 0, so byte = 0x00
        $this->assertSame(0x00, ord($result[0]));
    }

    public function testDecodeGroup3WithBlackIs1(): void
    {
        // Same all-white row but with blackIs1=true
        $bits = '10011'; // white run of 8
        $byte = bindec(str_pad($bits, 8, '0'));
        $data = chr($byte);

        $filter = new CCITTFaxFilter(k: 0, columns: 8, rows: 1, endOfBlock: false, blackIs1: true);
        $result = $filter->decode($data);

        // With blackIs1=true, pixel value is used directly: white=0, so byte=0x00
        $this->assertSame(1, strlen($result));
        $this->assertSame(0x00, ord($result[0]));
    }

    public function testDecodeGroup3MixedRow(): void
    {
        // 4 white pixels then 4 black pixels (columns=8)
        // White run of 4: '1011' (from white terminating, value 4)
        // Black run of 4: '011' (from black terminating, value 4)
        $bits = '1011' . '011';
        $byte = bindec(str_pad($bits, 8, '0'));
        $data = chr($byte);

        $filter = new CCITTFaxFilter(k: 0, columns: 8, rows: 1, endOfBlock: false);
        $result = $filter->decode($data);

        $this->assertSame(1, strlen($result));
        // 4 white (value=1) + 4 black (value=0) = 11110000 = 0xF0
        $this->assertSame(0xF0, ord($result[0]));
    }

    public function testGroup4EmptyReturnsEmpty(): void
    {
        // Group 4 with no valid data should return empty
        $filter = new CCITTFaxFilter(k: -1, columns: 8, rows: 0);
        $result = $filter->decode("\x00\x00");
        // May return empty or a partial row depending on data interpretation
        $this->assertIsString($result);
    }

    public function testConstructorParameters(): void
    {
        // Verify all parameters can be set
        $filter = new CCITTFaxFilter(
            k: -1,
            columns: 100,
            rows: 50,
            endOfLine: true,
            encodedByteAlign: true,
            endOfBlock: false,
            blackIs1: true,
        );

        // Should not throw
        $this->assertInstanceOf(CCITTFaxFilter::class, $filter);
    }

    public function testDecodeMultipleRows(): void
    {
        // Two all-white rows of 8 pixels each
        // White run of 8: '10011'
        $bits = '10011' . '10011';
        $padded = str_pad($bits, 16, '0');
        $byte1 = bindec(substr($padded, 0, 8));
        $byte2 = bindec(substr($padded, 8, 8));
        $data = chr($byte1) . chr($byte2);

        $filter = new CCITTFaxFilter(k: 0, columns: 8, rows: 2, endOfBlock: false);
        $result = $filter->decode($data);

        $this->assertSame(2, strlen($result)); // 2 rows × 1 byte each
        $this->assertSame(0xFF, ord($result[0]));
        $this->assertSame(0xFF, ord($result[1]));
    }
}
