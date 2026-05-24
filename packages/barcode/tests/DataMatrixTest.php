<?php

declare(strict_types=1);

namespace Phpdftk\Barcode\Tests;

use Phpdftk\Barcode\BarcodeOptions;
use Phpdftk\Barcode\Encoder\DataMatrix;
use Phpdftk\Barcode\Encoder\DataMatrixSpec;
use PHPUnit\Framework\TestCase;

class DataMatrixTest extends TestCase
{
    public function testShortInputProduces10x10Symbol(): void
    {
        // 2-char ASCII → 2 codewords → fits in 10×10 (3-cw capacity).
        $bitmap = DataMatrix::encode('AB', new BarcodeOptions());
        self::assertSame(10, $bitmap->rows());
        self::assertSame(10, $bitmap->columns());
    }

    public function testDigitPairCompressionShrinksSymbol(): void
    {
        // 8 digits compress to 4 codewords (2 digits → 1 cw) → 14×14.
        // 8 letters (no compression) → 8 codewords → 16×16.
        $digits = DataMatrix::encode('12345678', new BarcodeOptions());
        $letters = DataMatrix::encode('ABCDEFGH', new BarcodeOptions());
        self::assertLessThan($letters->rows(), $digits->rows());
    }

    public function testFinderLOnBottomAndLeft(): void
    {
        $bitmap = DataMatrix::encode('HELLO', new BarcodeOptions());
        $size = $bitmap->rows();
        $m = $bitmap->modules;
        // Bottom row entirely dark.
        for ($c = 0; $c < $size; $c++) {
            self::assertTrue($m[$size - 1][$c], "bottom row col {$c} should be dark");
        }
        // Left column entirely dark.
        for ($r = 0; $r < $size; $r++) {
            self::assertTrue($m[$r][0], "left col row {$r} should be dark");
        }
    }

    public function testTopRowTimingAlternates(): void
    {
        $bitmap = DataMatrix::encode('HELLO', new BarcodeOptions());
        $size = $bitmap->rows();
        $m = $bitmap->modules;
        for ($c = 0; $c < $size; $c++) {
            $expected = $c % 2 === 0;
            self::assertSame($expected, $m[0][$c], "top row col {$c} expected " . ($expected ? 'dark' : 'light'));
        }
    }

    public function testRightColumnTimingAlternates(): void
    {
        $bitmap = DataMatrix::encode('HELLO', new BarcodeOptions());
        $size = $bitmap->rows();
        $m = $bitmap->modules;
        for ($r = 0; $r < $size; $r++) {
            // Bottom-right is dark (finder corner); pattern alternates upward.
            $expected = ($size - 1 - $r) % 2 === 0;
            self::assertSame($expected, $m[$r][$size - 1], "right col row {$r} expected " . ($expected ? 'dark' : 'light'));
        }
    }

    public function testSymbolSizeGrowsWithPayload(): void
    {
        $small = DataMatrix::encode('A', new BarcodeOptions());
        $medium = DataMatrix::encode(str_repeat('A', 30), new BarcodeOptions());
        $large = DataMatrix::encode(str_repeat('A', 150), new BarcodeOptions());
        self::assertLessThan($medium->rows(), $small->rows());
        self::assertLessThan($large->rows(), $medium->rows());
    }

    public function testMultiRegionSymbolForLargerPayloads(): void
    {
        // 70+ codewords forces a multi-region symbol (32×32 starts the 2×2 family).
        $bitmap = DataMatrix::encode(str_repeat('A', 65), new BarcodeOptions());
        self::assertGreaterThanOrEqual(32, $bitmap->rows());
        // Symbol size must be a valid Data Matrix square.
        $valid = array_column(DataMatrixSpec::SQUARE_SIZES, 'size');
        self::assertContains($bitmap->rows(), $valid);
    }

    public function testDeterministicForSameInput(): void
    {
        $a = DataMatrix::encode('REPEAT 123', new BarcodeOptions());
        $b = DataMatrix::encode('REPEAT 123', new BarcodeOptions());
        self::assertSame($a->modules, $b->modules);
    }

    public function testDifferentInputsProduceDifferentMatrices(): void
    {
        $a = DataMatrix::encode('ALPHA', new BarcodeOptions());
        $b = DataMatrix::encode('BETA', new BarcodeOptions());
        // Both 12×12 — payload differs, the data-region modules differ.
        self::assertNotSame($a->modules, $b->modules);
    }

    public function testEmptyInputThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        DataMatrix::encode('', new BarcodeOptions());
    }

    public function testPayloadTooLargeForLargestSymbolThrows(): void
    {
        // Largest 144×144 holds 1558 codewords; 4000 chars of unique
        // ASCII (no digit-pair compression) is 4000 codewords.
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('exceeds');
        DataMatrix::encode(str_repeat('A', 4000), new BarcodeOptions());
    }

    public function testHighByteUsesUpperShift(): void
    {
        // The 'é' byte (0xE9 in WinAnsi-ish UTF-8 last byte) requires
        // upper-shift codeword 235 → two codewords for one byte.
        $highByte = chr(0xE9);
        $a = DataMatrix::encode('A', new BarcodeOptions());
        $b = DataMatrix::encode('A' . $highByte, new BarcodeOptions());
        // 'A' alone fits in a tiny symbol; 'A' + 0xE9 takes one
        // more codeword (235 + value), still fits 10×10.
        self::assertGreaterThanOrEqual($a->rows(), $b->rows());
    }

    public function testAllSquareSizesCanBeSelected(): void
    {
        // Walk through capacities and confirm each entry can be hit.
        foreach (DataMatrixSpec::SQUARE_SIZES as $entry) {
            $payload = str_repeat('A', $entry['dataCodewords']);
            $bitmap = DataMatrix::encode($payload, new BarcodeOptions());
            self::assertSame($entry['size'], $bitmap->rows(), "expected size {$entry['size']} for {$entry['dataCodewords']}-codeword payload");
        }
    }
}
