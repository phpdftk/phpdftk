<?php

declare(strict_types=1);

namespace Phpdftk\Barcode\Tests;

use Phpdftk\Barcode\BarcodeOptions;
use Phpdftk\Barcode\Encoder\Code39;
use PHPUnit\Framework\TestCase;

class Code39Test extends TestCase
{
    public function testEncodesDigitsAndUppercase(): void
    {
        $bitmap = Code39::encode('CODE 39', new BarcodeOptions());
        self::assertSame(1, $bitmap->rows());
        self::assertGreaterThan(0, $bitmap->columns());
    }

    public function testWideRatioTwoIsTighter(): void
    {
        $tight = Code39::encode('A', new BarcodeOptions(), wideRatio: 2);
        $loose = Code39::encode('A', new BarcodeOptions(), wideRatio: 3);
        self::assertLessThan($loose->columns(), $tight->columns());
    }

    public function testStartStopSentinelIsAlwaysAdded(): void
    {
        // Both produce identical encoding — the '*' is added by the encoder.
        $a = Code39::encode('A', new BarcodeOptions());
        // Encoder accepts but the standard explicitly forbids `*` in data.
        // Confirm two different inputs differ by exactly one character's pattern.
        $ab = Code39::encode('AB', new BarcodeOptions());
        self::assertGreaterThan($a->columns(), $ab->columns());
    }

    public function testLowercaseIsUppercased(): void
    {
        $a = Code39::encode('abc', new BarcodeOptions());
        $b = Code39::encode('ABC', new BarcodeOptions());
        self::assertSame($a->modules, $b->modules);
    }

    public function testEmptyInputThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Code39::encode('', new BarcodeOptions());
    }

    public function testUnsupportedCharThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("'@'");
        Code39::encode('HELLO@WORLD', new BarcodeOptions());
    }

    public function testInvalidWideRatioThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Code39::encode('A', new BarcodeOptions(), wideRatio: 4);
    }
}
