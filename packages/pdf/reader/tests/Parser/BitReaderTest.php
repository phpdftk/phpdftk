<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Reader\Tests\Parser;

use ApprLabs\Pdf\Reader\Parser\BitReader;
use PHPUnit\Framework\TestCase;

class BitReaderTest extends TestCase
{
    public function testReadSingleBit(): void
    {
        // 0b10000000 = 0x80
        $reader = new BitReader("\x80");
        $this->assertSame(1, $reader->readBits(1));
        $this->assertSame(0, $reader->readBits(1));
    }

    public function testReadFullByte(): void
    {
        $reader = new BitReader("\xA5"); // 10100101
        $this->assertSame(0xA5, $reader->readBits(8));
    }

    public function testReadAcrossBytesBoundary(): void
    {
        // 0xFF 0x00 = 11111111 00000000
        $reader = new BitReader("\xFF\x00");
        $reader->readBits(4); // consume 1111
        $val = $reader->readBits(8); // read 1111 0000 = 0xF0
        $this->assertSame(0xF0, $val);
    }

    public function testReadZeroBits(): void
    {
        $reader = new BitReader("\xFF");
        $this->assertSame(0, $reader->readBits(0));
        // Position should not advance
        $this->assertSame(0, $reader->getBitPosition());
    }

    public function testAlignToByte(): void
    {
        $reader = new BitReader("\xFF\x00");
        $reader->readBits(3);
        $this->assertSame(3, $reader->getBitPosition());

        $reader->alignToByte();
        $this->assertSame(8, $reader->getBitPosition());
        $this->assertSame(1, $reader->getBytePosition());
    }

    public function testAlignToByteWhenAlreadyAligned(): void
    {
        $reader = new BitReader("\xFF\x00");
        $reader->readBits(8);
        $reader->alignToByte();
        $this->assertSame(8, $reader->getBitPosition());
    }

    public function testReadPastEndThrows(): void
    {
        $reader = new BitReader("\xFF");
        $reader->readBits(8);

        $this->expectException(\RuntimeException::class);
        $reader->readBits(1);
    }

    public function testRead16BitValue(): void
    {
        // 0x1234 in big-endian = 0x12 0x34
        $reader = new BitReader("\x12\x34");
        $this->assertSame(0x1234, $reader->readBits(16));
    }

    public function testReadVariousWidths(): void
    {
        // 0b11010110 = 0xD6
        $reader = new BitReader("\xD6");
        $this->assertSame(0b110, $reader->readBits(3)); // 110
        $this->assertSame(0b10, $reader->readBits(2));   // 10
        $this->assertSame(0b110, $reader->readBits(3));  // 110
    }
}
