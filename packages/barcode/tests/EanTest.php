<?php

declare(strict_types=1);

namespace Phpdftk\Barcode\Tests;

use Phpdftk\Barcode\BarcodeOptions;
use Phpdftk\Barcode\Encoder\Ean;
use PHPUnit\Framework\TestCase;

class EanTest extends TestCase
{
    public function testEan13EncodesTo95Modules(): void
    {
        $bitmap = Ean::encodeEan13('590123412345', new BarcodeOptions());
        self::assertSame(1, $bitmap->rows());
        // EAN-13 is exactly 95 modules: 3 + 6×7 + 5 + 6×7 + 3.
        self::assertSame(95, $bitmap->columns());
    }

    public function testEan8EncodesTo67Modules(): void
    {
        $bitmap = Ean::encodeEan8('1234567', new BarcodeOptions());
        self::assertSame(67, $bitmap->columns());
    }

    public function testUpcAEncodesTo95Modules(): void
    {
        $bitmap = Ean::encodeUpcA('12345678901', new BarcodeOptions());
        self::assertSame(95, $bitmap->columns());
    }

    public function testKnownChecksum(): void
    {
        // The classic "590123412345" + checksum 7 — well-documented example.
        self::assertSame(7, Ean::checksum('590123412345'));
        // Wikipedia EAN-13 example "400638133393" → checksum 1.
        self::assertSame(1, Ean::checksum('400638133393'));
    }

    public function testEan13AcceptsAlreadyChecksummedInput(): void
    {
        // 5901234123457 includes the checksum.
        $a = Ean::encodeEan13('590123412345', new BarcodeOptions());
        $b = Ean::encodeEan13('5901234123457', new BarcodeOptions());
        self::assertSame($a->modules, $b->modules);
    }

    public function testEan13BadChecksumThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('checksum mismatch');
        Ean::encodeEan13('5901234123450', new BarcodeOptions());
    }

    public function testEan13RejectsBadLength(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Ean::encodeEan13('123', new BarcodeOptions());
    }

    public function testEan13RejectsNonDigits(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Ean::encodeEan13('59012341234A', new BarcodeOptions());
    }

    public function testEan8RejectsBadLength(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Ean::encodeEan8('12345', new BarcodeOptions());
    }

    public function testUpcAAccepts11Or12Digits(): void
    {
        // Real UPC-A: 11 payload digits + computed checksum.
        // Checksum of 72527273070: weighted sum 7×3+2×1+5×3+2×1+7×3+2×1+7×3+3×1+0×3+7×1+0×3 = 94
        // → (10 - 94 % 10) % 10 = 6, giving full code 725272730706.
        $a = Ean::encodeUpcA('72527273070', new BarcodeOptions());
        $b = Ean::encodeUpcA('725272730706', new BarcodeOptions());
        self::assertSame($a->modules, $b->modules);
    }

    public function testEan13StartsAndEndsWithDarkBar(): void
    {
        $bitmap = Ean::encodeEan13('123456789012', new BarcodeOptions());
        $row = $bitmap->modules[0];
        self::assertTrue($row[0], 'EAN-13 starts with bar (101 guard)');
        self::assertTrue($row[count($row) - 1], 'EAN-13 ends with bar (101 guard)');
    }
}
