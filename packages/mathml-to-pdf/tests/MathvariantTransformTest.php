<?php

declare(strict_types=1);

namespace Phpdftk\MathmlToPdf\Tests;

use Phpdftk\MathmlToPdf\MathvariantTransform;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the `mathvariant` → Mathematical Alphanumeric
 * Symbols transformation.
 */
final class MathvariantTransformTest extends TestCase
{
    public function testNormalIsIdentity(): void
    {
        self::assertSame('x', MathvariantTransform::apply('x', 'normal'));
        self::assertSame('abc', MathvariantTransform::apply('abc', 'normal'));
    }

    public function testUnknownVariantPassesThrough(): void
    {
        self::assertSame('abc', MathvariantTransform::apply('abc', 'banana'));
        self::assertSame('abc', MathvariantTransform::apply('abc', ''));
    }

    public function testBoldUppercaseMapping(): void
    {
        // A (U+0041) -> U+1D400 (MATHEMATICAL BOLD CAPITAL A)
        self::assertSame("\u{1D400}", MathvariantTransform::apply('A', 'bold'));
        // Z (U+005A) -> U+1D419
        self::assertSame("\u{1D419}", MathvariantTransform::apply('Z', 'bold'));
    }

    public function testBoldLowercaseMapping(): void
    {
        // x (U+0078) -> U+1D431 (MATHEMATICAL BOLD SMALL X)
        self::assertSame("\u{1D431}", MathvariantTransform::apply('x', 'bold'));
    }

    public function testBoldDigitsMapping(): void
    {
        // 0 (U+0030) -> U+1D7CE (MATHEMATICAL BOLD DIGIT ZERO)
        self::assertSame("\u{1D7CE}", MathvariantTransform::apply('0', 'bold'));
        self::assertSame("\u{1D7D7}", MathvariantTransform::apply('9', 'bold'));
    }

    public function testItalicXMapping(): void
    {
        // x (U+0078) -> U+1D465 (MATHEMATICAL ITALIC SMALL X)
        self::assertSame("\u{1D465}", MathvariantTransform::apply('x', 'italic'));
    }

    public function testItalicHIsPlanckOverride(): void
    {
        // h is the standard hole - maps to U+210E PLANCK CONSTANT
        // instead of the reserved U+1D455 slot.
        self::assertSame("\u{210E}", MathvariantTransform::apply('h', 'italic'));
    }

    public function testItalicDigitsUnchanged(): void
    {
        // No italic digit forms exist; the rule maps 'digit' to null
        // so digits pass through unchanged.
        self::assertSame('5', MathvariantTransform::apply('5', 'italic'));
    }

    public function testScriptCapitalHolesMapToHistoricCodepoints(): void
    {
        self::assertSame("\u{212C}", MathvariantTransform::apply('B', 'script'));
        self::assertSame("\u{2130}", MathvariantTransform::apply('E', 'script'));
        self::assertSame("\u{210B}", MathvariantTransform::apply('H', 'script'));
        self::assertSame("\u{2110}", MathvariantTransform::apply('I', 'script'));
        self::assertSame("\u{2112}", MathvariantTransform::apply('L', 'script'));
        self::assertSame("\u{2133}", MathvariantTransform::apply('M', 'script'));
        self::assertSame("\u{211B}", MathvariantTransform::apply('R', 'script'));
    }

    public function testScriptLowercaseHolesMapToHistoricCodepoints(): void
    {
        self::assertSame("\u{212F}", MathvariantTransform::apply('e', 'script'));
        self::assertSame("\u{210A}", MathvariantTransform::apply('g', 'script'));
        self::assertSame("\u{2134}", MathvariantTransform::apply('o', 'script'));
    }

    public function testScriptNonHoleMapping(): void
    {
        // 'A' has no hole. A -> U+1D49C.
        self::assertSame("\u{1D49C}", MathvariantTransform::apply('A', 'script'));
    }

    public function testFrakturHolesMapToHistoricCodepoints(): void
    {
        self::assertSame("\u{212D}", MathvariantTransform::apply('C', 'fraktur'));
        self::assertSame("\u{210C}", MathvariantTransform::apply('H', 'fraktur'));
        self::assertSame("\u{2111}", MathvariantTransform::apply('I', 'fraktur'));
        self::assertSame("\u{211C}", MathvariantTransform::apply('R', 'fraktur'));
        self::assertSame("\u{2128}", MathvariantTransform::apply('Z', 'fraktur'));
    }

    public function testDoubleStruckHolesMapToHistoricCodepoints(): void
    {
        self::assertSame("\u{2102}", MathvariantTransform::apply('C', 'double-struck'));
        self::assertSame("\u{210D}", MathvariantTransform::apply('H', 'double-struck'));
        self::assertSame("\u{2115}", MathvariantTransform::apply('N', 'double-struck'));
        self::assertSame("\u{2119}", MathvariantTransform::apply('P', 'double-struck'));
        self::assertSame("\u{211A}", MathvariantTransform::apply('Q', 'double-struck'));
        self::assertSame("\u{211D}", MathvariantTransform::apply('R', 'double-struck'));
        self::assertSame("\u{2124}", MathvariantTransform::apply('Z', 'double-struck'));
    }

    public function testDoubleStruckDigits(): void
    {
        // 0 -> U+1D7D8 (DOUBLE-STRUCK DIGIT ZERO)
        self::assertSame("\u{1D7D8}", MathvariantTransform::apply('0', 'double-struck'));
    }

    public function testSansSerifMapping(): void
    {
        // A -> U+1D5A0, 0 -> U+1D7E2
        self::assertSame("\u{1D5A0}", MathvariantTransform::apply('A', 'sans-serif'));
        self::assertSame("\u{1D7E2}", MathvariantTransform::apply('0', 'sans-serif'));
    }

    public function testMonospaceMapping(): void
    {
        // A -> U+1D670, 0 -> U+1D7F6
        self::assertSame("\u{1D670}", MathvariantTransform::apply('A', 'monospace'));
        self::assertSame("\u{1D7F6}", MathvariantTransform::apply('0', 'monospace'));
    }

    public function testBoldItalicMapping(): void
    {
        // A -> U+1D468
        self::assertSame("\u{1D468}", MathvariantTransform::apply('A', 'bold-italic'));
    }

    public function testBoldSansSerifItalicMapping(): void
    {
        // A -> U+1D63C
        self::assertSame(
            "\u{1D63C}",
            MathvariantTransform::apply('A', 'sans-serif-bold-italic'),
        );
    }

    public function testNonAsciiCodepointsPassThrough(): void
    {
        // Greek letter alpha (U+03B1) and Hebrew letter aleph
        // (U+05D0) aren't in our scope for v1; they pass through.
        self::assertSame(
            "\u{03B1}\u{05D0}",
            MathvariantTransform::apply("\u{03B1}\u{05D0}", 'bold'),
        );
    }

    public function testMixedAsciiAndPunctuation(): void
    {
        // 'A=1' under bold: 'A' -> 1D400, '=' passes through, '1' -> 1D7CF.
        self::assertSame(
            "\u{1D400}=\u{1D7CF}",
            MathvariantTransform::apply('A=1', 'bold'),
        );
    }

    public function testVariantCaseInsensitive(): void
    {
        // The attribute value is matched case-insensitively per
        // MathML conventions.
        self::assertSame(
            "\u{1D400}",
            MathvariantTransform::apply('A', 'BOLD'),
        );
        self::assertSame(
            "\u{1D400}",
            MathvariantTransform::apply('A', 'Bold'),
        );
    }

    public function testSupports(): void
    {
        self::assertTrue(MathvariantTransform::supports('normal'));
        self::assertTrue(MathvariantTransform::supports('bold'));
        self::assertTrue(MathvariantTransform::supports('double-struck'));
        self::assertFalse(MathvariantTransform::supports('banana'));
        self::assertFalse(MathvariantTransform::supports(''));
    }
}
