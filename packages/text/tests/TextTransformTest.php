<?php

declare(strict_types=1);

namespace Phpdftk\Text\Tests;

use Phpdftk\Text\TextTransform;
use PHPUnit\Framework\TestCase;

/**
 * CSS Text 3 §2.1 / CSS Text 4 §2.1.4 — `text-transform`.
 *
 * Shared by HTML inline layout and the SVG `<text>` painter; the
 * algorithm previously existed only in the former.
 */
final class TextTransformTest extends TestCase
{
    public function testNullKeywordLeavesTextAlone(): void
    {
        self::assertSame('Hello', TextTransform::apply('Hello', null));
    }

    public function testNoneLeavesTextAlone(): void
    {
        self::assertSame('Hello', TextTransform::apply('Hello', 'none'));
    }

    public function testUnknownKeywordLeavesTextAlone(): void
    {
        // Guard: an unimplemented transform must pass through, not
        // silently degrade to one of the implemented ones.
        self::assertSame('Hello', TextTransform::apply('Hello', 'full-size-kana'));
    }

    public function testUppercaseAndLowercase(): void
    {
        self::assertSame('HELLO, WORLD!', TextTransform::apply('Hello, World!', 'uppercase'));
        self::assertSame('hello, world!', TextTransform::apply('Hello, World!', 'lowercase'));
    }

    public function testCaseMappingIsUnicodeAware(): void
    {
        self::assertSame('ÉTÉ', TextTransform::apply('été', 'uppercase'));
    }

    public function testCapitalizeUpperCasesEachWordAndKeepsSeparators(): void
    {
        self::assertSame('Hello, World!', TextTransform::apply('hello, world!', 'capitalize'));
        self::assertSame("A\n B", TextTransform::apply("a\n b", 'capitalize'));
    }

    public function testCapitalizeLeavesTheRestOfAWordAlone(): void
    {
        // Guard: `capitalize` touches only the first grapheme, so an
        // already-shouting word keeps its shape.
        self::assertSame('MacDonald', TextTransform::apply('macDonald', 'capitalize'));
    }

    public function testFullWidthMapsAsciiAndSpace(): void
    {
        self::assertSame('Ａ！', TextTransform::apply('A!', 'full-width'));
        self::assertSame("\u{3000}", TextTransform::apply(' ', 'full-width'));
    }

    public function testFullWidthLeavesNonAsciiAlone(): void
    {
        self::assertSame('あ', TextTransform::apply('あ', 'full-width'));
    }

    public function testKeywordMatchingIsCaseAndWhitespaceInsensitive(): void
    {
        self::assertSame('HI', TextTransform::apply('hi', ' UpperCase '));
    }

    public function testEmptyInputIsReturnedUnchanged(): void
    {
        self::assertSame('', TextTransform::apply('', 'uppercase'));
    }
}
