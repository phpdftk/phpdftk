<?php

declare(strict_types=1);

namespace Phpdftk\Filters\Tests;

use PHPUnit\Framework\TestCase;
use Phpdftk\Filters\FlateFilter;
use Phpdftk\Filters\Ascii85Filter;
use Phpdftk\Filters\AsciiHexFilter;
use Phpdftk\Filters\RunLengthFilter;

class FilterTest extends TestCase
{
    // -----------------------------------------------------------------------
    // FlateFilter
    // -----------------------------------------------------------------------

    public function testFlateEncodeDecodeEmpty(): void
    {
        $f = new FlateFilter();
        $this->assertSame('', $f->decode($f->encode('')));
    }

    public function testFlateEncodeDecodeSingleByte(): void
    {
        $f = new FlateFilter();
        $data = "\x42";
        $this->assertSame($data, $f->decode($f->encode($data)));
    }

    public function testFlateEncodeDecodeAllZeros(): void
    {
        $f = new FlateFilter();
        $data = str_repeat("\x00", 1000);
        $this->assertSame($data, $f->decode($f->encode($data)));
    }

    public function testFlateEncodeDecodeText(): void
    {
        $f = new FlateFilter();
        $data = 'Hello, World! This is a test of the flate filter with some repeated text. Hello, World!';
        $this->assertSame($data, $f->decode($f->encode($data)));
    }

    public function testFlateCompressionLevels(): void
    {
        $data = str_repeat('ABCD', 500);
        for ($level = 1; $level <= 9; $level++) {
            $f = new FlateFilter($level);
            $this->assertSame($data, $f->decode($f->encode($data)));
        }
    }

    // -----------------------------------------------------------------------
    // Ascii85Filter
    // -----------------------------------------------------------------------

    public function testAscii85EncodeDecodeEmpty(): void
    {
        $f = new Ascii85Filter();
        $this->assertSame('', $f->decode($f->encode('')));
    }

    public function testAscii85EncodeDecodeSingleByte(): void
    {
        $f = new Ascii85Filter();
        $data = "\x42";
        $this->assertSame($data, $f->decode($f->encode($data)));
    }

    public function testAscii85EncodeDecodeAllZeros(): void
    {
        $f = new Ascii85Filter();
        $data = str_repeat("\x00", 8); // 2 full groups of all zeros → 'zz~>'
        $encoded = $f->encode($data);
        $this->assertStringContainsString('z', $encoded);
        $this->assertSame($data, $f->decode($encoded));
    }

    public function testAscii85EncodeDecodeText(): void
    {
        $f = new Ascii85Filter();
        $data = 'Hello, World!';
        $this->assertSame($data, $f->decode($f->encode($data)));
    }

    public function testAscii85EncodeDecodeRandom(): void
    {
        $f = new Ascii85Filter();
        $data = '';
        for ($i = 0; $i < 100; $i++) {
            $data .= chr($i);
        }
        $this->assertSame($data, $f->decode($f->encode($data)));
    }

    public function testAscii85EncodedEndsWithTilde(): void
    {
        $f = new Ascii85Filter();
        $encoded = $f->encode('test');
        $this->assertStringEndsWith('~>', $encoded);
    }

    // -----------------------------------------------------------------------
    // AsciiHexFilter
    // -----------------------------------------------------------------------

    public function testAsciiHexEncodeDecodeEmpty(): void
    {
        $f = new AsciiHexFilter();
        $this->assertSame('', $f->decode($f->encode('')));
    }

    public function testAsciiHexEncodeDecodeSingleByte(): void
    {
        $f = new AsciiHexFilter();
        $data = "\xFF";
        $this->assertSame($data, $f->decode($f->encode($data)));
    }

    public function testAsciiHexEncodeDecodeAllZeros(): void
    {
        $f = new AsciiHexFilter();
        $data = str_repeat("\x00", 10);
        $this->assertSame($data, $f->decode($f->encode($data)));
    }

    public function testAsciiHexEncodeDecodeText(): void
    {
        $f = new AsciiHexFilter();
        $data = 'Hello, World!';
        $this->assertSame($data, $f->decode($f->encode($data)));
    }

    public function testAsciiHexEncodeFormat(): void
    {
        $f = new AsciiHexFilter();
        $encoded = $f->encode("\xAB\xCD");
        $this->assertSame('abcd>', $encoded);
    }

    public function testAsciiHexDecodeWithWhitespace(): void
    {
        $f = new AsciiHexFilter();
        // Hex with embedded whitespace should still decode
        $this->assertSame("\xAB\xCD", $f->decode("AB CD>"));
    }

    // -----------------------------------------------------------------------
    // RunLengthFilter
    // -----------------------------------------------------------------------

    public function testRunLengthEncodeDecodeEmpty(): void
    {
        $f = new RunLengthFilter();
        $this->assertSame('', $f->decode($f->encode('')));
    }

    public function testRunLengthEncodeDecodeSingleByte(): void
    {
        $f = new RunLengthFilter();
        $data = "\x42";
        $this->assertSame($data, $f->decode($f->encode($data)));
    }

    public function testRunLengthEncodeDecodeAllZeros(): void
    {
        $f = new RunLengthFilter();
        $data = str_repeat("\x00", 100);
        $this->assertSame($data, $f->decode($f->encode($data)));
    }

    public function testRunLengthEncodeDecodeText(): void
    {
        $f = new RunLengthFilter();
        $data = 'Hello, World! AAAAAAAAAA test test test';
        $this->assertSame($data, $f->decode($f->encode($data)));
    }

    public function testRunLengthEncodeDecodeRandom(): void
    {
        $f = new RunLengthFilter();
        $data = '';
        for ($i = 0; $i < 200; $i++) {
            $data .= chr($i % 256);
        }
        $this->assertSame($data, $f->decode($f->encode($data)));
    }

    public function testRunLengthEncodedEndsWithEod(): void
    {
        $f = new RunLengthFilter();
        $encoded = $f->encode('test');
        $this->assertSame(128, ord($encoded[strlen($encoded) - 1]));
    }

    public function testRunLengthDecodeExplicit(): void
    {
        // Manual test: length byte 0 = 1 literal byte 'A', then EOD
        $f = new RunLengthFilter();
        $encoded = chr(0) . 'A' . chr(128);
        $this->assertSame('A', $f->decode($encoded));
    }

    public function testRunLengthDecodeRepeated(): void
    {
        // Manual test: length byte 254 = 257-254=3 repeats of 'B', then EOD
        $f = new RunLengthFilter();
        $encoded = chr(254) . 'B' . chr(128);
        $this->assertSame('BBB', $f->decode($encoded));
    }
}
