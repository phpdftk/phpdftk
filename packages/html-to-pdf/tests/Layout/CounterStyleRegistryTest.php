<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf\Tests\Layout;

use Phpdftk\HtmlToPdf\Layout\CounterStyleDefinition;
use Phpdftk\HtmlToPdf\Layout\CounterStyleRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * CSS Counter Styles 3 — the §3 counter systems and the §6 predefined
 * style catalogue, exercised directly on known value → representation
 * pairs taken from the spec's own examples and from the CSS test suite's
 * reference files.
 *
 * Testing the algorithms here rather than through rendered output is
 * deliberate: a wrong symbol table shows up as one character in a PNG
 * diff, but as a readable assertion failure at this level.
 */
final class CounterStyleRegistryTest extends TestCase
{
    private CounterStyleRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new CounterStyleRegistry();
    }

    // -----------------------------------------------------------------
    // Failure modes first: values a system cannot represent must fall
    // back, not produce a wrong-but-plausible string.
    // -----------------------------------------------------------------

    /** @return iterable<string, array{0: int, 1: string, 2: string}> */
    public static function outOfRangeProvider(): iterable
    {
        // §3.4 alphabetic and §3.3 symbolic have an auto range of 1 to
        // infinity, so zero and negatives defer to `fallback: decimal`.
        yield 'lower-greek zero' => [0, 'lower-greek', '0'];
        yield 'lower-greek negative' => [-3, 'lower-greek', '-3'];
        yield 'lower-alpha zero' => [0, 'lower-alpha', '0'];
        // §6.2 roman is additive over 1..3999.
        yield 'lower-roman zero' => [0, 'lower-roman', '0'];
        yield 'upper-roman above range' => [4000, 'upper-roman', '4000'];
        yield 'upper-roman negative' => [-1, 'upper-roman', '-1'];
        // hebrew is declared `range: 1 10999`.
        yield 'hebrew above range' => [11000, 'hebrew', '11000'];
        // armenian is declared `range: 1 9999`.
        yield 'armenian above range' => [10000, 'armenian', '10000'];
        // georgian is declared `range: 1 19999`.
        yield 'georgian above range' => [20000, 'georgian', '20000'];
        // cjk-decimal is declared `range: 0 infinite`.
        yield 'cjk-decimal negative' => [-5, 'cjk-decimal', '-5'];
        // §3.2 fixed runs out after its last symbol.
        yield 'cjk-earthly-branch past last symbol' => [13, 'cjk-earthly-branch', '13'];
        yield 'cjk-heavenly-stem past last symbol' => [11, 'cjk-heavenly-stem', '11'];
    }

    #[DataProvider('outOfRangeProvider')]
    public function testOutOfRangeValuesUseTheFallbackStyle(int $value, string $style, string $expected): void
    {
        self::assertSame(
            $expected,
            $this->registry->representation($value, $style),
            'a value the style cannot represent must come from `fallback`',
        );
    }

    public function testUnknownStyleNameFallsBackToDecimal(): void
    {
        self::assertSame('42', $this->registry->representation(42, 'no-such-style'));
        self::assertSame('42. ', $this->registry->marker(42, 'no-such-style'));
        self::assertFalse($this->registry->has('no-such-style'));
    }

    public function testFallbackLoopTerminates(): void
    {
        // Two author styles that name each other as `fallback` must not
        // recurse forever; the guard returns the plain decimal string.
        $a = new CounterStyleDefinition(
            system: CounterStyleDefinition::ALPHABETIC,
            symbols: ['a', 'b'],
            rangeMin: 5,
            rangeMax: 6,
            fallback: 'loop-b',
        );
        $b = new CounterStyleDefinition(
            system: CounterStyleDefinition::ALPHABETIC,
            symbols: ['c', 'd'],
            rangeMin: 5,
            rangeMax: 6,
            fallback: 'loop-a',
        );
        $registry = new CounterStyleRegistry(['loop-a' => $a, 'loop-b' => $b]);
        self::assertSame('1', $registry->representation(1, 'loop-a'));
    }

    public function testEmptySymbolListCannotGenerate(): void
    {
        $registry = new CounterStyleRegistry([
            'empty' => new CounterStyleDefinition(
                system: CounterStyleDefinition::CYCLIC,
                symbols: [],
            ),
        ]);
        self::assertSame('3', $registry->representation(3, 'empty'));
    }

    public function testAdditiveWithAnUnreachableRemainderFallsBack(): void
    {
        // §3.6 — if the greedy pass leaves a non-zero remainder the value
        // is not representable. 3 cannot be built from {2}.
        $registry = new CounterStyleRegistry([
            'evens' => new CounterStyleDefinition(
                system: CounterStyleDefinition::ADDITIVE,
                additiveSymbols: [[2, 'x']],
            ),
        ]);
        self::assertSame('xx', $registry->representation(4, 'evens'));
        self::assertSame('3', $registry->representation(3, 'evens'));
    }

    // -----------------------------------------------------------------
    // The five systems (§3).
    // -----------------------------------------------------------------

    public function testCyclicWrapsInBothDirections(): void
    {
        $registry = new CounterStyleRegistry([
            'tri' => new CounterStyleDefinition(
                system: CounterStyleDefinition::CYCLIC,
                symbols: ['a', 'b', 'c'],
            ),
        ]);
        self::assertSame('a', $registry->representation(1, 'tri'));
        self::assertSame('c', $registry->representation(3, 'tri'));
        self::assertSame('a', $registry->representation(4, 'tri'));
        // A cyclic style has an infinite auto range and no negative sign:
        // the value indexes the cycle, so 0 is the symbol before the first.
        self::assertSame('c', $registry->representation(0, 'tri'));
        self::assertSame('b', $registry->representation(-1, 'tri'));
    }

    public function testSymbolicRepeatsTheSymbol(): void
    {
        $registry = new CounterStyleRegistry([
            'foot' => new CounterStyleDefinition(
                system: CounterStyleDefinition::SYMBOLIC,
                symbols: ['*', "\u{2020}"],
            ),
        ]);
        self::assertSame('*', $registry->representation(1, 'foot'));
        self::assertSame("\u{2020}", $registry->representation(2, 'foot'));
        self::assertSame('**', $registry->representation(3, 'foot'));
        self::assertSame("\u{2020}\u{2020}", $registry->representation(4, 'foot'));
        self::assertSame('***', $registry->representation(5, 'foot'));
    }

    public function testAlphabeticIsBijective(): void
    {
        self::assertSame('a', $this->registry->representation(1, 'lower-alpha'));
        self::assertSame('z', $this->registry->representation(26, 'lower-alpha'));
        self::assertSame('aa', $this->registry->representation(27, 'lower-alpha'));
        self::assertSame('az', $this->registry->representation(52, 'lower-alpha'));
        self::assertSame('ba', $this->registry->representation(53, 'lower-alpha'));
        self::assertSame('AA', $this->registry->representation(27, 'upper-latin'));
        // 24 Greek letters, so 25 is the first two-letter representation.
        self::assertSame("\u{03B1}\u{03B1}", $this->registry->representation(25, 'lower-greek'));
    }

    public function testNumericIsPositionalAndSpellsZero(): void
    {
        self::assertSame('0', $this->registry->representation(0, 'decimal'));
        self::assertSame('100', $this->registry->representation(100, 'decimal'));
        // §6.2 cjk-decimal — 〇 一 二 ... as positional digits.
        self::assertSame("\u{4E8C}\u{4E09}", $this->registry->representation(23, 'cjk-decimal'));
        self::assertSame("\u{4E00}\u{3007}\u{3007}", $this->registry->representation(100, 'cjk-decimal'));
    }

    public function testFixedStopsAtItsLastSymbol(): void
    {
        self::assertSame("\u{5B50}", $this->registry->representation(1, 'cjk-earthly-branch'));
        self::assertSame("\u{4EA5}", $this->registry->representation(12, 'cjk-earthly-branch'));
    }

    // -----------------------------------------------------------------
    // Descriptors: negative, pad (§4.3, §4.6).
    // -----------------------------------------------------------------

    public function testNegativeSignWrapsTheAbsoluteRepresentation(): void
    {
        $registry = new CounterStyleRegistry([
            'paren' => new CounterStyleDefinition(
                system: CounterStyleDefinition::NUMERIC,
                symbols: ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
                negativePrefix: '(',
                negativeSuffix: ')',
            ),
        ]);
        self::assertSame('(12)', $registry->representation(-12, 'paren'));
        self::assertSame('12', $registry->representation(12, 'paren'));
    }

    public function testPadCountsTheNegativeSignAndSitsInsideIt(): void
    {
        // Mirrors css-counter-styles descriptor-pad: `pad: 3 '*'` over
        // upper-roman gives -III, -II, -*I, **I, *II, III.
        $registry = new CounterStyleRegistry([
            'padded' => new CounterStyleDefinition(
                system: CounterStyleDefinition::ADDITIVE,
                additiveSymbols: [[10, 'X'], [9, 'IX'], [5, 'V'], [4, 'IV'], [1, 'I']],
                rangeMin: PHP_INT_MIN,
                rangeMax: 5,
                padLength: 3,
                padSymbol: '*',
            ),
        ]);
        self::assertSame('-III', $registry->representation(-3, 'padded'));
        self::assertSame('-II', $registry->representation(-2, 'padded'));
        self::assertSame('-*I', $registry->representation(-1, 'padded'));
        self::assertSame('**I', $registry->representation(1, 'padded'));
        self::assertSame('*II', $registry->representation(2, 'padded'));
        self::assertSame('III', $registry->representation(3, 'padded'));
        // Out of range: the FALLBACK generates the representation, so the
        // padding of the original style must not be applied to it.
        self::assertSame('6', $registry->representation(6, 'padded'));
    }

    public function testDecimalLeadingZeroIsDecimalWithPadTwo(): void
    {
        self::assertSame('01', $this->registry->representation(1, 'decimal-leading-zero'));
        self::assertSame('99', $this->registry->representation(99, 'decimal-leading-zero'));
        self::assertSame('100', $this->registry->representation(100, 'decimal-leading-zero'));
    }

    // -----------------------------------------------------------------
    // Prefix / suffix (§4.7) — markers only, never `counter()`.
    // -----------------------------------------------------------------

    /** @return iterable<string, array{0: string, 1: int, 2: string}> */
    public static function markerProvider(): iterable
    {
        yield 'decimal takes the initial ". "' => ['decimal', 1, '1. '];
        yield 'bengali takes the initial ". "' => ['bengali', 1, "\u{09E7}. "];
        yield 'cjk-decimal uses the ideographic comma' => ['cjk-decimal', 1, "\u{4E00}\u{3001}"];
        yield 'hiragana uses the ideographic comma' => ['hiragana', 1, "\u{3042}\u{3001}"];
        yield 'cjk-earthly-branch uses the ideographic comma'
            => ['cjk-earthly-branch', 1, "\u{5B50}\u{3001}"];
        yield 'disc uses a bare space' => ['disc', 1, "\u{2022} "];
    }

    #[DataProvider('markerProvider')]
    public function testMarkerAppliesThePerStyleSuffix(string $style, int $value, string $expected): void
    {
        self::assertSame($expected, $this->registry->marker($value, $style));
    }

    public function testRepresentationNeverCarriesTheSuffix(): void
    {
        // `content: counter(n, cjk-decimal)` must not emit the 、 that the
        // marker does.
        self::assertSame("\u{4E00}", $this->registry->representation(1, 'cjk-decimal'));
        self::assertSame("\u{2022}", $this->registry->representation(1, 'disc'));
    }

    // -----------------------------------------------------------------
    // The §6 predefined catalogue, spot-checked against the CSS test
    // suite's reference files.
    // -----------------------------------------------------------------

    /** @return iterable<string, array{0: string, 1: int, 2: string}> */
    public static function predefinedProvider(): iterable
    {
        yield 'arabic-indic 1' => ['arabic-indic', 1, "\u{0661}"];
        yield 'bengali 9' => ['bengali', 9, "\u{09EF}"];
        yield 'cambodian 1' => ['cambodian', 1, "\u{17E1}"];
        yield 'khmer 1' => ['khmer', 1, "\u{17E1}"];
        yield 'devanagari 1' => ['devanagari', 1, "\u{0967}"];
        yield 'gujarati 1' => ['gujarati', 1, "\u{0AE7}"];
        yield 'gurmukhi 1' => ['gurmukhi', 1, "\u{0A67}"];
        yield 'kannada 1' => ['kannada', 1, "\u{0CE7}"];
        yield 'lao 1' => ['lao', 1, "\u{0ED1}"];
        yield 'malayalam 1' => ['malayalam', 1, "\u{0D67}"];
        yield 'mongolian 7' => ['mongolian', 7, "\u{1817}"];
        yield 'myanmar 1' => ['myanmar', 1, "\u{1041}"];
        yield 'oriya 1' => ['oriya', 1, "\u{0B67}"];
        yield 'persian 1' => ['persian', 1, "\u{06F1}"];
        yield 'tamil 1' => ['tamil', 1, "\u{0BE7}"];
        yield 'telugu 1' => ['telugu', 1, "\u{0C67}"];
        yield 'thai 1' => ['thai', 1, "\u{0E51}"];
        yield 'tibetan 1' => ['tibetan', 1, "\u{0F21}"];
        // Two-digit values exercise the positional assembly, not just the
        // symbol table.
        yield 'thai 25' => ['thai', 25, "\u{0E52}\u{0E55}"];
        yield 'tibetan 10' => ['tibetan', 10, "\u{0F21}\u{0F20}"];
        // Alphabetic kana orderings.
        yield 'hiragana 1' => ['hiragana', 1, "\u{3042}"];
        yield 'hiragana-iroha 1' => ['hiragana-iroha', 1, "\u{3044}"];
        yield 'katakana 1' => ['katakana', 1, "\u{30A2}"];
        yield 'katakana-iroha 1' => ['katakana-iroha', 1, "\u{30A4}"];
        // Fixed CJK sequences.
        yield 'cjk-heavenly-stem 1' => ['cjk-heavenly-stem', 1, "\u{7532}"];
        yield 'cjk-heavenly-stem 10' => ['cjk-heavenly-stem', 10, "\u{7678}"];
        // Additive alphabets.
        yield 'hebrew 15 is not theophoric' => ['hebrew', 15, "\u{05D8}\u{05D5}"];
        yield 'hebrew 16 is not theophoric' => ['hebrew', 16, "\u{05D8}\u{05D6}"];
        yield 'hebrew 1' => ['hebrew', 1, "\u{05D0}"];
        yield 'armenian 3' => ['armenian', 3, "\u{0533}"];
        yield 'lower-armenian 3' => ['lower-armenian', 3, "\u{0563}"];
        yield 'georgian 7' => ['georgian', 7, "\u{10D6}"];
        yield 'lower-roman 1999' => ['lower-roman', 1999, 'mcmxcix'];
        yield 'upper-roman 2026' => ['upper-roman', 2026, 'MMXXVI'];
    }

    #[DataProvider('predefinedProvider')]
    public function testPredefinedStyle(string $style, int $value, string $expected): void
    {
        self::assertSame($expected, $this->registry->representation($value, $style));
    }

    // -----------------------------------------------------------------
    // §7.1 East Asian styles. Every expectation below is transcribed from
    // the CSS test suite's own reference files, which spell the marker out
    // as literal text.
    // -----------------------------------------------------------------

    /** @return iterable<string, array{0: string, 1: int, 2: string}> */
    public static function cjkProvider(): iterable
    {
        // Informal Japanese drops the leading 1 before EVERY place marker
        // and never inserts a zero filler.
        yield 'ja-informal 0' => ['japanese-informal', 0, "\u{3007}"];
        yield 'ja-informal 10' => ['japanese-informal', 10, "\u{5341}"];
        yield 'ja-informal 11' => ['japanese-informal', 11, "\u{5341}\u{4E00}"];
        yield 'ja-informal 100' => ['japanese-informal', 100, "\u{767E}"];
        yield 'ja-informal 101' => ['japanese-informal', 101, "\u{767E}\u{4E00}"];
        yield 'ja-informal 1000' => ['japanese-informal', 1000, "\u{5343}"];
        yield 'ja-informal 1005' => ['japanese-informal', 1005, "\u{5343}\u{4E94}"];
        yield 'ja-informal 1060' => ['japanese-informal', 1060, "\u{5343}\u{516D}\u{5341}"];
        yield 'ja-informal 9999' => [
            'japanese-informal', 9999,
            "\u{4E5D}\u{5343}\u{4E5D}\u{767E}\u{4E5D}\u{5341}\u{4E5D}",
        ];
        // Formal Japanese keeps the 1 and uses the formal digits.
        yield 'ja-formal 10' => ['japanese-formal', 10, "\u{58F1}\u{62FE}"];
        yield 'ja-formal 100' => ['japanese-formal', 100, "\u{58F1}\u{767E}"];
        yield 'ja-formal 1000' => ['japanese-formal', 1000, "\u{58F1}\u{9621}"];
        yield 'ja-formal 1005' => ['japanese-formal', 1005, "\u{58F1}\u{9621}\u{4F0D}"];
        yield 'ja-formal 540' => ['japanese-formal', 540, "\u{4F0D}\u{767E}\u{56DB}\u{62FE}"];

        // Chinese inserts the zero filler for skipped interior places —
        // the behaviour a plain additive system cannot express.
        yield 'simp-informal 101' => [
            'simp-chinese-informal', 101, "\u{4E00}\u{767E}\u{96F6}\u{4E00}",
        ];
        yield 'simp-informal 1005' => [
            'simp-chinese-informal', 1005, "\u{4E00}\u{5343}\u{96F6}\u{4E94}",
        ];
        yield 'simp-informal 1060' => [
            'simp-chinese-informal', 1060, "\u{4E00}\u{5343}\u{96F6}\u{516D}\u{5341}",
        ];
        // ... but not for TRAILING ones: 1800 is 一千八百, not 一千八百零零.
        yield 'simp-informal 1800' => [
            'simp-chinese-informal', 1800, "\u{4E00}\u{5343}\u{516B}\u{767E}",
        ];
        // Informal Chinese drops the 1 only in a LEADING tens place.
        yield 'simp-informal 10' => ['simp-chinese-informal', 10, "\u{5341}"];
        yield 'simp-informal 100' => ['simp-chinese-informal', 100, "\u{4E00}\u{767E}"];
        yield 'simp-informal 1000' => ['simp-chinese-informal', 1000, "\u{4E00}\u{5343}"];
        yield 'trad-informal 101' => [
            'trad-chinese-informal', 101, "\u{4E00}\u{767E}\u{96F6}\u{4E00}",
        ];
        yield 'simp-formal 101' => [
            'simp-chinese-formal', 101, "\u{58F9}\u{4F70}\u{96F6}\u{58F9}",
        ];
        yield 'trad-formal 1065' => [
            'trad-chinese-formal', 1065,
            "\u{58F9}\u{4EDF}\u{96F6}\u{9678}\u{62FE}\u{4F0D}",
        ];

        // Korean: no zero filler, formal keeps the 1, hanja-informal drops it.
        yield 'ko-hangul 1005' => [
            'korean-hangul-formal', 1005, "\u{C77C}\u{CC9C}\u{C624}",
        ];
        yield 'ko-hangul 10' => ['korean-hangul-formal', 10, "\u{C77C}\u{C2ED}"];
        yield 'ko-hanja-informal 1005' => [
            'korean-hanja-informal', 1005, "\u{5343}\u{4E94}",
        ];
        yield 'ko-hanja-formal 1005' => [
            'korean-hanja-formal', 1005, "\u{58F9}\u{4EDF}\u{4E94}",
        ];
    }

    #[DataProvider('cjkProvider')]
    public function testEastAsianStyle(string $style, int $value, string $expected): void
    {
        self::assertSame($expected, $this->registry->representation($value, $style));
    }

    /** @return iterable<string, array{0: string, 1: string}> */
    public static function cjkNegativeProvider(): iterable
    {
        yield 'japanese-informal' => ['japanese-informal', "\u{30DE}\u{30A4}\u{30CA}\u{30B9}"];
        yield 'japanese-formal' => ['japanese-formal', "\u{30DE}\u{30A4}\u{30CA}\u{30B9}"];
        yield 'simp-chinese-informal' => ['simp-chinese-informal', "\u{8D1F}"];
        yield 'trad-chinese-informal' => ['trad-chinese-informal', "\u{8CA0}"];
        yield 'korean-hangul-formal' => [
            'korean-hangul-formal', "\u{B9C8}\u{C774}\u{B108}\u{C2A4} ",
        ];
    }

    #[DataProvider('cjkNegativeProvider')]
    public function testEastAsianNegativePrefix(string $style, string $prefix): void
    {
        self::assertSame(
            $prefix . $this->registry->representation(10, $style),
            $this->registry->representation(-10, $style),
            'a negative value is the prefix plus the representation of its magnitude',
        );
    }

    /** @return iterable<string, array{0: string, 1: string}> */
    public static function cjkOutOfRangeProvider(): iterable
    {
        // §7.1 declares `range: -9999 9999`. The optional extended range
        // (万 / 億 / 兆) is deliberately NOT implemented, so 10000 takes the
        // fallback — cjk-decimal for Chinese and Japanese, decimal for
        // Korean, which declares none.
        yield 'japanese-informal' => [
            'japanese-informal', "\u{4E00}\u{3007}\u{3007}\u{3007}\u{3007}",
        ];
        yield 'simp-chinese-formal' => [
            'simp-chinese-formal', "\u{4E00}\u{3007}\u{3007}\u{3007}\u{3007}",
        ];
        yield 'korean-hangul-formal' => ['korean-hangul-formal', '10000'];
        yield 'korean-hanja-informal' => ['korean-hanja-informal', '10000'];
    }

    #[DataProvider('cjkOutOfRangeProvider')]
    public function testEastAsianOutOfRangeFallback(string $style, string $expected): void
    {
        self::assertSame($expected, $this->registry->representation(10000, $style));
    }

    public function testEastAsianMarkerKeepsItsOwnSuffixThroughAFallback(): void
    {
        // The reference file for css3-counter-styles-054 shows `10000, ` —
        // the fallback supplies the SYMBOLS, the original style still
        // supplies the affixes.
        self::assertSame('10000, ', $this->registry->marker(10000, 'korean-hangul-formal'));
        self::assertSame(
            "\u{4E00}\u{3007}\u{3007}\u{3007}\u{3007}\u{3001}",
            $this->registry->marker(10000, 'japanese-informal'),
        );
    }
}
