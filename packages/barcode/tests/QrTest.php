<?php

declare(strict_types=1);

namespace Phpdftk\Barcode\Tests;

use Phpdftk\Barcode\BarcodeOptions;
use Phpdftk\Barcode\Encoder\Qr;
use Phpdftk\Barcode\Encoder\QrSpec;
use PHPUnit\Framework\TestCase;

class QrTest extends TestCase
{
    public function testShortNumericInputProducesV1Matrix(): void
    {
        $bitmap = Qr::encode('1234', new BarcodeOptions());
        // V1 matrix is 21 × 21.
        self::assertSame(21, $bitmap->rows());
        self::assertSame(21, $bitmap->columns());
    }

    public function testFinderPatternsArePresent(): void
    {
        $bitmap = Qr::encode('TEST', new BarcodeOptions());
        $m = $bitmap->modules;
        $size = $bitmap->rows();
        // All three finder corners should have a dark module at (0,0)/(0,size-7)/(size-7,0).
        self::assertTrue($m[0][0]);
        self::assertTrue($m[0][$size - 7]);
        self::assertTrue($m[$size - 7][0]);
        // Each finder centre (3,3 within its 7×7) is dark.
        self::assertTrue($m[3][3]);
        self::assertTrue($m[3][$size - 4]);
        self::assertTrue($m[$size - 4][3]);
    }

    public function testTimingPatternAlternates(): void
    {
        $bitmap = Qr::encode('AB', new BarcodeOptions());
        $m = $bitmap->modules;
        // Row 6 between cols 8..size-8 alternates dark/light.
        for ($i = 8; $i < $bitmap->columns() - 8; $i++) {
            self::assertSame($i % 2 === 0, $m[6][$i], "timing pattern broken at col {$i}");
        }
    }

    public function testDarkModuleIsPresent(): void
    {
        // The "dark module" at (size-8, 8) is always set (ISO 18004 §6.3.4).
        $bitmap = Qr::encode('AB', new BarcodeOptions());
        $size = $bitmap->rows();
        self::assertTrue($bitmap->modules[$size - 8][8]);
    }

    public function testLongerInputGrowsToHigherVersion(): void
    {
        $short = Qr::encode('HELLO', new BarcodeOptions());
        $long = Qr::encode(str_repeat('HELLO WORLD ', 8), new BarcodeOptions());
        self::assertGreaterThan($short->rows(), $long->rows());
    }

    public function testEccLevelHIncreasesMatrixSize(): void
    {
        $optsL = new BarcodeOptions();
        $optsH = new BarcodeOptions();
        $low = Qr::encode('HELLO WORLD', $optsL, QrSpec::ECC_L);
        $high = Qr::encode('HELLO WORLD', $optsH, QrSpec::ECC_H);
        // Same payload, more ECC = same or larger version.
        self::assertGreaterThanOrEqual($low->rows(), $high->rows());
    }

    public function testAlphanumericPayloadFitsSmaller(): void
    {
        $bytes = Qr::encode('hello world', new BarcodeOptions());
        $alpha = Qr::encode('HELLO WORLD', new BarcodeOptions());
        // Alphanumeric mode packs more efficiently than byte mode for
        // long enough strings — even tiny inputs should still fit in
        // the same or smaller version with the alphanumeric mode.
        self::assertLessThanOrEqual($bytes->rows(), $alpha->rows());
    }

    public function testEmptyInputThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Qr::encode('', new BarcodeOptions());
    }

    public function testInvalidEccLevelThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Qr::encode('test', new BarcodeOptions(), eccLevel: 4);
    }

    public function testOversizedPayloadThrows(): void
    {
        // A payload that won't fit in V40 even at level L (~2950 bytes
        // capacity). 8000 bytes is well beyond.
        $this->expectException(\RuntimeException::class);
        Qr::encode(str_repeat('A', 8000), new BarcodeOptions(), QrSpec::ECC_L);
    }

    public function testUrlEncodesAndIsValidQrSize(): void
    {
        $bitmap = Qr::encode('https://phpdftk.dev/', new BarcodeOptions());
        // Matrix must be (4V + 17) modules for some V; verify the
        // dimension is one of the legal QR sizes.
        $size = $bitmap->rows();
        self::assertGreaterThanOrEqual(21, $size);
        self::assertSame(0, ($size - 17) % 4);
    }

    public function testQrSpecCharCountBitsByMode(): void
    {
        // Version-1 numeric uses 10 bits; alphanumeric uses 9; byte uses 8.
        self::assertSame(10, QrSpec::charCountBits(1, 0b0001));
        self::assertSame(9, QrSpec::charCountBits(1, 0b0010));
        self::assertSame(8, QrSpec::charCountBits(1, 0b0100));
        // V27 (> 26) byte mode uses 16 bits.
        self::assertSame(16, QrSpec::charCountBits(27, 0b0100));
    }
}
