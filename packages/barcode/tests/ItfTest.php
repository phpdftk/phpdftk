<?php

declare(strict_types=1);

namespace Phpdftk\Barcode\Tests;

use Phpdftk\Barcode\BarcodeOptions;
use Phpdftk\Barcode\Encoder\Itf;
use PHPUnit\Framework\TestCase;

class ItfTest extends TestCase
{
    public function testEncodesEvenLengthDigits(): void
    {
        $bitmap = Itf::encode('12345678', new BarcodeOptions());
        self::assertSame(1, $bitmap->rows());
        // 4 start guard modules + 4 digit pairs × (5N + 5W) at ratio 1:2:
        //   each pair contributes 5 bars (sum 7) + 5 spaces (sum 7) = 14 modules
        //   so 4 pairs = 56 modules.
        // Stop guard: wideRatio + 1 + 1 = 4 modules.
        // Total: 4 + 56 + 4 = 64.
        self::assertSame(64, $bitmap->columns());
    }

    public function testOddLengthThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('even');
        Itf::encode('12345', new BarcodeOptions());
    }

    public function testNonDigitInputThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Itf::encode('12AB', new BarcodeOptions());
    }

    public function testEmptyInputThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Itf::encode('', new BarcodeOptions());
    }

    public function testInvalidWideRatioThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Itf::encode('12', new BarcodeOptions(), wideRatio: 1);
    }
}
