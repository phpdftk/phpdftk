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

    public function testLowerGreekFirstLetter(): void
    {
        self::assertSame("\u{03B1}", CounterFormat::format(1, 'lower-greek')); // α
    }

    public function testLowerGreekLastSingleLetter(): void
    {
        // 24 = ω (omega, last letter).
        self::assertSame("\u{03C9}", CounterFormat::format(24, 'lower-greek'));
    }

    public function testLowerGreekUsesSigmaNotFinalSigma(): void
    {
        // CSS Counter Styles 3 explicitly uses U+03C3 (σ), not U+03C2 (ς).
        // 18th letter = sigma.
        self::assertSame("\u{03C3}", CounterFormat::format(18, 'lower-greek'));
    }

    public function testLowerGreekBijectiveBeyondAlphabet(): void
    {
        // After 24, the alphabetic system bijectively recurses: 25 = αα.
        self::assertSame("\u{03B1}\u{03B1}", CounterFormat::format(25, 'lower-greek'));
        // 48 = αω.
        self::assertSame("\u{03B1}\u{03C9}", CounterFormat::format(48, 'lower-greek'));
        // 49 = βα.
        self::assertSame("\u{03B2}\u{03B1}", CounterFormat::format(49, 'lower-greek'));
    }

    public function testLowerGreekBelowOneFallsBackToDecimal(): void
    {
        // Negative: invalid (zero or negative) ordinals fall back to
        // the raw decimal string for safety.
        self::assertSame('0', CounterFormat::format(0, 'lower-greek'));
        self::assertSame('-3', CounterFormat::format(-3, 'lower-greek'));
    }
}
