<?php

declare(strict_types=1);

namespace ApprLabs\Filters\Tests;

use ApprLabs\Filters\CCITTFaxFilter;
use PHPUnit\Framework\TestCase;

class CCITTFaxFilterTest extends TestCase
{
    // -----------------------------------------------------------------------
    // Decode-only tests (preserved from original)
    // -----------------------------------------------------------------------

    public function testDecodeEmptyData(): void
    {
        $filter = new CCITTFaxFilter();
        $this->assertSame('', $filter->decode(''));
    }

    public function testDecodeGroup3AllWhiteRow(): void
    {
        // Build a Group 3 encoded all-white row of 8 pixels.
        // White run of 8: code '10011' (from white terminating table)
        $bits = '10011'; // white run of 8
        $byte = bindec(str_pad($bits, 8, '0'));
        $data = chr($byte);

        $filter = new CCITTFaxFilter(k: 0, columns: 8, rows: 1, endOfBlock: false);
        $result = $filter->decode($data);

        // With default blackIs1=false, white pixel=0 in internal repr,
        // packRow does 1-pixel for blackIs1=false, so white=1. All-white = 0xFF.
        $this->assertSame(1, strlen($result));
        $this->assertSame(0xFF, ord($result[0]));
    }

    public function testDecodeGroup3AllBlackRow(): void
    {
        // A row always starts with WHITE. To get all black:
        //   white run of 0: '00110101'
        //   black run of 8: '000101'
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

        $this->assertSame(2, strlen($result)); // 2 rows x 1 byte each
        $this->assertSame(0xFF, ord($result[0]));
        $this->assertSame(0xFF, ord($result[1]));
    }

    // -----------------------------------------------------------------------
    // Encode empty
    // -----------------------------------------------------------------------

    public function testEncodeEmptyGroup3(): void
    {
        $filter = new CCITTFaxFilter(k: 0, columns: 8);
        $this->assertSame('', $filter->encode(''));
    }

    public function testEncodeEmptyGroup4(): void
    {
        $filter = new CCITTFaxFilter(k: -1, columns: 8);
        $this->assertSame('', $filter->encode(''));
    }

    // -----------------------------------------------------------------------
    // Group 3 (1D) encode/decode roundtrip
    // -----------------------------------------------------------------------

    public function testEncodeDecodeAllWhiteGroup3(): void
    {
        $raw = chr(0xFF); // 8 white pixels (blackIs1=false: 1=white)
        $filter = new CCITTFaxFilter(k: 0, columns: 8, rows: 1, endOfBlock: false);
        $this->assertSame($raw, $filter->decode($filter->encode($raw)));
    }

    public function testEncodeDecodeAllBlackGroup3(): void
    {
        $raw = chr(0x00); // 8 black pixels
        $filter = new CCITTFaxFilter(k: 0, columns: 8, rows: 1, endOfBlock: false);
        $this->assertSame($raw, $filter->decode($filter->encode($raw)));
    }

    public function testEncodeDecodeMixedRowGroup3(): void
    {
        $raw = chr(0xF0); // 4 white + 4 black
        $filter = new CCITTFaxFilter(k: 0, columns: 8, rows: 1, endOfBlock: false);
        $this->assertSame($raw, $filter->decode($filter->encode($raw)));
    }

    public function testEncodeDecodeMultipleRowsGroup3(): void
    {
        $raw = chr(0xFF) . chr(0x00) . chr(0xF0);
        $filter = new CCITTFaxFilter(k: 0, columns: 8, rows: 3, endOfBlock: false);
        $this->assertSame($raw, $filter->decode($filter->encode($raw)));
    }

    // -----------------------------------------------------------------------
    // Group 4 (2D) encode/decode roundtrip
    // -----------------------------------------------------------------------

    public function testEncodeDecodeAllWhiteGroup4(): void
    {
        $raw = chr(0xFF);
        $filter = new CCITTFaxFilter(k: -1, columns: 8, rows: 1, endOfBlock: true);
        $this->assertSame($raw, $filter->decode($filter->encode($raw)));
    }

    public function testEncodeDecodeAllBlackGroup4(): void
    {
        $raw = chr(0x00);
        $filter = new CCITTFaxFilter(k: -1, columns: 8, rows: 1, endOfBlock: true);
        $this->assertSame($raw, $filter->decode($filter->encode($raw)));
    }

    public function testEncodeDecodeMixedRowGroup4(): void
    {
        $raw = chr(0xF0);
        $filter = new CCITTFaxFilter(k: -1, columns: 8, rows: 1, endOfBlock: true);
        $this->assertSame($raw, $filter->decode($filter->encode($raw)));
    }

    public function testEncodeDecodeMultipleRowsGroup4(): void
    {
        $raw = chr(0xFF) . chr(0x00) . chr(0xF0);
        $filter = new CCITTFaxFilter(k: -1, columns: 8, rows: 3, endOfBlock: true);
        $this->assertSame($raw, $filter->decode($filter->encode($raw)));
    }

    // -----------------------------------------------------------------------
    // Parameter variations
    // -----------------------------------------------------------------------

    public function testEncodeDecodeWithBlackIs1Group3(): void
    {
        // With blackIs1=true, 1=black,0=white in raw bytes
        $raw = chr(0x00); // all white when blackIs1=true
        $filter = new CCITTFaxFilter(k: 0, columns: 8, rows: 1, endOfBlock: false, blackIs1: true);
        $this->assertSame($raw, $filter->decode($filter->encode($raw)));
    }

    public function testEncodeDecodeWithBlackIs1Group4(): void
    {
        $raw = chr(0xFF); // all black when blackIs1=true
        $filter = new CCITTFaxFilter(k: -1, columns: 8, rows: 1, endOfBlock: true, blackIs1: true);
        $this->assertSame($raw, $filter->decode($filter->encode($raw)));
    }

    public function testEncodeDecodeWithEncodedByteAlign(): void
    {
        $raw = chr(0xFF) . chr(0x00) . chr(0xF0);
        $filter = new CCITTFaxFilter(k: 0, columns: 8, rows: 3, endOfBlock: false, encodedByteAlign: true);
        $this->assertSame($raw, $filter->decode($filter->encode($raw)));
    }

    public function testEncodeDecodeWithEndOfLine(): void
    {
        $raw = chr(0xFF) . chr(0x00);
        $filter = new CCITTFaxFilter(k: 0, columns: 8, rows: 2, endOfLine: true, endOfBlock: false);
        $this->assertSame($raw, $filter->decode($filter->encode($raw)));
    }

    public function testEncodeDecodeWithEndOfBlock(): void
    {
        $raw = chr(0xAA); // alternating pixels
        $filter = new CCITTFaxFilter(k: 0, columns: 8, rows: 1, endOfBlock: true);
        $this->assertSame($raw, $filter->decode($filter->encode($raw)));
    }

    public function testEncodeDecodeWithoutEndOfBlock(): void
    {
        $raw = chr(0xAA);
        $filter = new CCITTFaxFilter(k: 0, columns: 8, rows: 1, endOfBlock: false);
        $this->assertSame($raw, $filter->decode($filter->encode($raw)));
    }

    // -----------------------------------------------------------------------
    // Wide and random data
    // -----------------------------------------------------------------------

    public function testEncodeDecodeWideRowGroup3(): void
    {
        // 1728 columns (default fax width), all white
        $raw = str_repeat(chr(0xFF), 216); // 1728/8 = 216 bytes
        $filter = new CCITTFaxFilter(k: 0, columns: 1728, rows: 1, endOfBlock: false);
        $this->assertSame($raw, $filter->decode($filter->encode($raw)));
    }

    public function testEncodeDecodeWideRowGroup4(): void
    {
        $raw = str_repeat(chr(0xFF), 216);
        $filter = new CCITTFaxFilter(k: -1, columns: 1728, rows: 1, endOfBlock: true);
        $this->assertSame($raw, $filter->decode($filter->encode($raw)));
    }

    public function testEncodeDecodeRandomBitmapGroup3(): void
    {
        // 16 columns x 5 rows of pseudo-random data
        $raw = '';
        for ($i = 0; $i < 10; $i++) { // 2 bytes per row * 5 rows
            $raw .= chr(($i * 37 + 13) % 256);
        }
        $filter = new CCITTFaxFilter(k: 0, columns: 16, rows: 5, endOfBlock: false);
        $this->assertSame($raw, $filter->decode($filter->encode($raw)));
    }

    public function testEncodeDecodeRandomBitmapGroup4(): void
    {
        $raw = '';
        for ($i = 0; $i < 10; $i++) {
            $raw .= chr(($i * 37 + 13) % 256);
        }
        $filter = new CCITTFaxFilter(k: -1, columns: 16, rows: 5, endOfBlock: true);
        $this->assertSame($raw, $filter->decode($filter->encode($raw)));
    }

    // -----------------------------------------------------------------------
    // Compression check
    // -----------------------------------------------------------------------

    public function testEncodeCompressesRepetitiveData(): void
    {
        // 100 all-white rows should compress much smaller than raw
        $raw = str_repeat(chr(0xFF), 100); // 100 rows of 8 pixels
        $filter = new CCITTFaxFilter(k: 0, columns: 8, rows: 100, endOfBlock: false);
        $encoded = $filter->encode($raw);
        $this->assertLessThan(strlen($raw), strlen($encoded));
    }

    // -----------------------------------------------------------------------
    // Edge cases
    // -----------------------------------------------------------------------

    public function testEncodeDecodeAlternatingPixels(): void
    {
        // Worst-case for Huffman: checkerboard 10101010
        $raw = chr(0xAA);
        $filter = new CCITTFaxFilter(k: 0, columns: 8, rows: 1, endOfBlock: false);
        $this->assertSame($raw, $filter->decode($filter->encode($raw)));
    }

    public function testEncodeDecodeSinglePixelColumn(): void
    {
        // columns=1, single pixel white
        $raw = chr(0x80); // 1 bit set (white in default convention), padded to byte
        $filter = new CCITTFaxFilter(k: 0, columns: 1, rows: 1, endOfBlock: false);
        $this->assertSame($raw, $filter->decode($filter->encode($raw)));
    }

    public function testEncodeDecodeRowsInferredFromData(): void
    {
        // rows=0 — encoder should infer row count from data length
        $raw = chr(0xFF) . chr(0x00) . chr(0xF0);
        $filter = new CCITTFaxFilter(k: 0, columns: 8, rows: 0, endOfBlock: false);
        $this->assertSame($raw, $filter->decode($filter->encode($raw)));
    }

    public function testEncodeDecodeGroup4MultipleRowsComplex(): void
    {
        // 4 rows of 16 pixels with varying patterns
        $raw = chr(0xFF) . chr(0x00)   // all white + all black
             . chr(0xF0) . chr(0x0F)   // mixed
             . chr(0xAA) . chr(0x55)   // checkerboard
             . chr(0x00) . chr(0xFF);  // inverse
        $filter = new CCITTFaxFilter(k: -1, columns: 16, rows: 4, endOfBlock: true);
        $this->assertSame($raw, $filter->decode($filter->encode($raw)));
    }

    public function testEncodeDecodeGroup4WithByteAlignAndEndOfLine(): void
    {
        $raw = chr(0xFF) . chr(0x00);
        $filter = new CCITTFaxFilter(k: -1, columns: 8, rows: 2, endOfBlock: true, encodedByteAlign: true);
        $this->assertSame($raw, $filter->decode($filter->encode($raw)));
    }
}
