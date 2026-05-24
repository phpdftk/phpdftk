<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf\Tests\Layout;

use Phpdftk\HtmlToPdf\Layout\CounterFormat;
use PHPUnit\Framework\TestCase;

final class CounterFormatTest extends TestCase
{
    public function testDecimalFormats(): void
    {
        self::assertSame('1', CounterFormat::format(1, 'decimal'));
        self::assertSame('42', CounterFormat::format(42, 'decimal'));
        self::assertSame('01', CounterFormat::format(1, 'decimal-leading-zero'));
        self::assertSame('99', CounterFormat::format(99, 'decimal-leading-zero'));
    }

    public function testAlphaFormatsBijectiveBase26(): void
    {
        self::assertSame('a', CounterFormat::format(1, 'lower-alpha'));
        self::assertSame('z', CounterFormat::format(26, 'lower-alpha'));
        self::assertSame('aa', CounterFormat::format(27, 'lower-alpha'));
        self::assertSame('az', CounterFormat::format(52, 'lower-alpha'));
        self::assertSame('ba', CounterFormat::format(53, 'lower-alpha'));
        self::assertSame('A', CounterFormat::format(1, 'upper-alpha'));
        self::assertSame('AA', CounterFormat::format(27, 'upper-latin'));
    }

    public function testRomanFormatsSubtractive(): void
    {
        self::assertSame('i', CounterFormat::format(1, 'lower-roman'));
        self::assertSame('iv', CounterFormat::format(4, 'lower-roman'));
        self::assertSame('ix', CounterFormat::format(9, 'lower-roman'));
        self::assertSame('xl', CounterFormat::format(40, 'lower-roman'));
        self::assertSame('mcmxcix', CounterFormat::format(1999, 'lower-roman'));
        self::assertSame('I', CounterFormat::format(1, 'upper-roman'));
        self::assertSame('MMXXVI', CounterFormat::format(2026, 'upper-roman'));
    }

    public function testRomanFallsBackToDecimalOutOfRange(): void
    {
        // Subtractive form only spans 1-3999; outside this range the
        // formatter falls back to the decimal string so callers still
        // get something meaningful.
        self::assertSame('0', CounterFormat::format(0, 'lower-roman'));
        self::assertSame('4000', CounterFormat::format(4000, 'upper-roman'));
    }

    public function testUnknownStyleFallsBackToDecimal(): void
    {
        // Browsers render unknown `list-style-type` as `decimal`; mirror
        // that fallback so callers don't have to special-case it.
        self::assertSame('7', CounterFormat::format(7, 'mongolian'));
        self::assertSame('7', CounterFormat::format(7, 'georgian'));
    }
}
