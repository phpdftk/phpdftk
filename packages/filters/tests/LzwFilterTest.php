<?php

declare(strict_types=1);

namespace Phpdftk\Filters\Tests;

use PHPUnit\Framework\TestCase;
use Phpdftk\Filters\LzwFilter;

class LzwFilterTest extends TestCase
{
    public function testEncodeDecodeEmpty(): void
    {
        $f = new LzwFilter();
        $this->assertSame('', $f->decode($f->encode('')));
    }

    public function testEncodeDecodeSingleByte(): void
    {
        $f = new LzwFilter();
        $data = 'A';
        $this->assertSame($data, $f->decode($f->encode($data)));
    }

    public function testEncodeDecodeRepeatedBytes(): void
    {
        $f = new LzwFilter();
        $data = str_repeat('A', 100);
        $this->assertSame($data, $f->decode($f->encode($data)));
    }

    public function testEncodeDecodeText(): void
    {
        $f = new LzwFilter();
        $data = 'Hello, World! This is a test of the LZW filter.';
        $this->assertSame($data, $f->decode($f->encode($data)));
    }

    public function testEncodeDecodeAllByteValues(): void
    {
        $f = new LzwFilter();
        $data = '';
        for ($i = 0; $i < 256; $i++) {
            $data .= chr($i);
        }
        $this->assertSame($data, $f->decode($f->encode($data)));
    }

    public function testEncodeDecodeLargeRepeating(): void
    {
        $f = new LzwFilter();
        $data = str_repeat('ABCD', 500);
        $this->assertSame($data, $f->decode($f->encode($data)));
    }

    public function testEncodedSmallerThanRepetitiveInput(): void
    {
        $f = new LzwFilter();
        $data = str_repeat('ABCDEF', 100);
        $encoded = $f->encode($data);
        // LZW should compress repetitive data
        $this->assertLessThan(strlen($data), strlen($encoded));
    }

    public function testEncodeDecodeBinaryData(): void
    {
        $f = new LzwFilter();
        $data = '';
        for ($i = 0; $i < 500; $i++) {
            $data .= chr(($i * 37) % 256);
        }
        $this->assertSame($data, $f->decode($f->encode($data)));
    }
}
