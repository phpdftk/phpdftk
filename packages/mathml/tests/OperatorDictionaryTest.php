<?php

declare(strict_types=1);

namespace Phpdftk\Mathml\Tests;

use Phpdftk\Mathml\OperatorDictionary;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the operator dictionary lookup table. We don't
 * exhaustively cover every operator (that's the spec's job); we
 * cover the structural invariants and a representative slice so
 * regressions in the data table show up loud.
 */
final class OperatorDictionaryTest extends TestCase
{
    public function testLookupReturnsDefaultEntryForUnknownOperator(): void
    {
        // Pure-ASCII garbage that no one would ever use as an
        // operator. Confirms the fallback path.
        $entry = OperatorDictionary::lookup('snorgle', 'infix');
        self::assertSame(OperatorDictionary::DEFAULT_ENTRY, $entry);
    }

    public function testLookupReturnsDefaultEntryForWrongForm(): void
    {
        // '+' is registered for infix only - looking it up as prefix
        // should fall through to the default.
        $entry = OperatorDictionary::lookup('+', 'prefix');
        self::assertSame(OperatorDictionary::DEFAULT_ENTRY, $entry);
    }

    public function testAdditiveOperatorsHaveMediumSpacing(): void
    {
        // 4/18 ~ 0.222 em on each side per Core Appendix B.
        $entry = OperatorDictionary::lookup('+', 'infix');
        self::assertEqualsWithDelta(4.0 / 18.0, $entry['lspace'], 0.001);
        self::assertEqualsWithDelta(4.0 / 18.0, $entry['rspace'], 0.001);
        self::assertFalse($entry['stretchy']);
    }

    public function testRelationalOperatorsHaveThickSpacing(): void
    {
        // 5/18 ~ 0.278 em per Core Appendix B.
        $entry = OperatorDictionary::lookup('=', 'infix');
        self::assertEqualsWithDelta(5.0 / 18.0, $entry['lspace'], 0.001);
        self::assertEqualsWithDelta(5.0 / 18.0, $entry['rspace'], 0.001);
    }

    public function testCommaHasAsymmetricSpacing(): void
    {
        // Comma: no lspace, thin rspace - the visual rule that
        // separates "(a, b)" cleanly.
        $entry = OperatorDictionary::lookup(',', 'infix');
        self::assertSame(0.0, $entry['lspace']);
        self::assertEqualsWithDelta(3.0 / 18.0, $entry['rspace'], 0.001);
    }

    public function testParenthesesAreStretchyPrefixAndPostfix(): void
    {
        $open = OperatorDictionary::lookup('(', 'prefix');
        $close = OperatorDictionary::lookup(')', 'postfix');
        self::assertTrue($open['stretchy']);
        self::assertTrue($close['stretchy']);
        // Brackets carry no spacing themselves.
        self::assertSame(0.0, $open['lspace']);
        self::assertSame(0.0, $open['rspace']);
    }

    public function testPipeOperatorHasAllThreeForms(): void
    {
        // `|` works as prefix, postfix, and infix - absolute value
        // bars on either side AND the conditional-probability bar
        // between siblings all need their own dictionary entries.
        $prefix = OperatorDictionary::lookup('|', 'prefix');
        $postfix = OperatorDictionary::lookup('|', 'postfix');
        $infix = OperatorDictionary::lookup('|', 'infix');
        self::assertTrue($prefix['stretchy']);
        self::assertTrue($postfix['stretchy']);
        self::assertFalse($infix['stretchy']);
        self::assertGreaterThan(0.0, $infix['lspace']);
    }

    public function testFactorialPostfixHasNoSpacing(): void
    {
        $entry = OperatorDictionary::lookup('!', 'postfix');
        self::assertSame(0.0, $entry['lspace']);
        self::assertSame(0.0, $entry['rspace']);
    }

    public function testSummationIsPrefixLargeop(): void
    {
        // U+2211 (n-ary summation).
        $entry = OperatorDictionary::lookup("\u{2211}", 'prefix');
        // Thin spacing on both sides per Core.
        self::assertEqualsWithDelta(3.0 / 18.0, $entry['lspace'], 0.001);
    }

    public function testMinusSignVariantBehavesLikeAsciiMinus(): void
    {
        // ASCII hyphen-minus and U+2212 MINUS SIGN must both resolve
        // to medium spacing - many MathML authors use one or the
        // other and the painter must not penalise either.
        $hyphen = OperatorDictionary::lookup('-', 'infix');
        $minus = OperatorDictionary::lookup("\u{2212}", 'infix');
        self::assertSame($hyphen['lspace'], $minus['lspace']);
        self::assertSame($hyphen['rspace'], $minus['rspace']);
    }

    public function testArrowsAreInfixWithThickSpacing(): void
    {
        $entry = OperatorDictionary::lookup("\u{2192}", 'infix');
        self::assertEqualsWithDelta(5.0 / 18.0, $entry['lspace'], 0.001);
        self::assertEqualsWithDelta(5.0 / 18.0, $entry['rspace'], 0.001);
    }

    public function testExpandedRelationalEntries(): void
    {
        // U+226A MUCH LESS-THAN, U+2225 PARALLEL TO, U+22A5 PERP
        // all carry thick spacing per Core Appendix B.
        $thick = 5.0 / 18.0;
        foreach (["\u{226A}", "\u{2225}", "\u{22A5}", "\u{223C}"] as $op) {
            $entry = OperatorDictionary::lookup($op, 'infix');
            self::assertEqualsWithDelta(
                $thick,
                $entry['lspace'],
                0.001,
                "infix lspace for $op should be thickmuskip",
            );
        }
    }

    public function testQuantifiersArePrefixWithThinRspace(): void
    {
        $thin = 3.0 / 18.0;
        foreach (["\u{2200}", "\u{2203}", "\u{2204}"] as $op) {
            $entry = OperatorDictionary::lookup($op, 'prefix');
            self::assertSame(0.0, $entry['lspace']);
            self::assertEqualsWithDelta($thin, $entry['rspace'], 0.001);
        }
    }

    public function testEllipsisAndDotsAreInfixWithZeroSpacing(): void
    {
        foreach (["\u{2026}", "\u{22EE}", "\u{22EF}", "\u{22F1}"] as $op) {
            $entry = OperatorDictionary::lookup($op, 'infix');
            self::assertSame(0.0, $entry['lspace']);
            self::assertSame(0.0, $entry['rspace']);
        }
    }

    public function testFloorAndCeilingBracketsAreStretchyAndPrefixOrPostfix(): void
    {
        // Floor + ceiling pairs: left side is prefix-stretchy, right
        // side is postfix-stretchy. Same as parens / brackets.
        $left = OperatorDictionary::lookup("\u{230A}", 'prefix');   // ⌊
        $right = OperatorDictionary::lookup("\u{230B}", 'postfix'); // ⌋
        self::assertTrue($left['stretchy']);
        self::assertTrue($right['stretchy']);
        $left = OperatorDictionary::lookup("\u{2308}", 'prefix');   // ⌈
        $right = OperatorDictionary::lookup("\u{2309}", 'postfix'); // ⌉
        self::assertTrue($left['stretchy']);
        self::assertTrue($right['stretchy']);
    }

    public function testPrimesArePostfixWithZeroSpacing(): void
    {
        foreach (["\u{2032}", "\u{2033}", "\u{2034}"] as $op) {
            $entry = OperatorDictionary::lookup($op, 'postfix');
            self::assertSame(0.0, $entry['lspace']);
            self::assertSame(0.0, $entry['rspace']);
        }
    }

    public function testCircledOperatorsHaveMediumSpacing(): void
    {
        $med = 4.0 / 18.0;
        foreach (["\u{2295}", "\u{2296}", "\u{2297}", "\u{2299}"] as $op) {
            $entry = OperatorDictionary::lookup($op, 'infix');
            self::assertEqualsWithDelta($med, $entry['lspace'], 0.001);
            self::assertEqualsWithDelta($med, $entry['rspace'], 0.001);
        }
    }

    public function testNAryUnionAndIntersectionArePrefixLargeOps(): void
    {
        $thin = 3.0 / 18.0;
        // U+22C3 (n-ary union) and U+22C2 (n-ary intersection) are
        // big-operator forms - prefix with thin spacing.
        foreach (["\u{22C2}", "\u{22C3}"] as $op) {
            $entry = OperatorDictionary::lookup($op, 'prefix');
            self::assertEqualsWithDelta($thin, $entry['lspace'], 0.001);
            self::assertEqualsWithDelta($thin, $entry['rspace'], 0.001);
        }
    }

    public function testNorm2VerticalLinesAreStretchyOnBothSides(): void
    {
        // U+2016 (double vertical line, ‖) is used as both opening
        // and closing fence in norm notation, so both prefix and
        // postfix entries must be stretchy.
        $prefix = OperatorDictionary::lookup("\u{2016}", 'prefix');
        $postfix = OperatorDictionary::lookup("\u{2016}", 'postfix');
        self::assertTrue($prefix['stretchy']);
        self::assertTrue($postfix['stretchy']);
    }

    public function testLargeOperatorsHaveLargeopAndMovableLimitsTrue(): void
    {
        foreach (["\u{2211}", "\u{220F}", "\u{222B}", "\u{22C3}", "\u{22C2}"] as $op) {
            $entry = OperatorDictionary::lookup($op, 'prefix');
            self::assertTrue(
                $entry['largeop'],
                "Expected largeop=true for $op",
            );
            self::assertTrue(
                $entry['movablelimits'],
                "Expected movablelimits=true for $op",
            );
        }
    }

    public function testNonLargeOperatorsHaveBothFlagsFalse(): void
    {
        // Regular operators - additive, multiplicative, relational -
        // should NOT auto-enable largeop or movablelimits.
        foreach (['+', '=', "\u{2208}", "\u{2192}"] as $op) {
            $entry = OperatorDictionary::lookup($op, 'infix');
            self::assertFalse($entry['largeop']);
            self::assertFalse($entry['movablelimits']);
        }
    }

    public function testBracketsHaveLargeopFalseDespiteBeingStretchy(): void
    {
        // Brackets stretch but they aren't large operators.
        $left = OperatorDictionary::lookup('(', 'prefix');
        self::assertTrue($left['stretchy']);
        self::assertFalse($left['largeop']);
        self::assertFalse($left['movablelimits']);
    }

    public function testDefaultEntryHasNewFlags(): void
    {
        self::assertArrayHasKey('largeop', OperatorDictionary::DEFAULT_ENTRY);
        self::assertArrayHasKey('movablelimits', OperatorDictionary::DEFAULT_ENTRY);
        self::assertFalse(OperatorDictionary::DEFAULT_ENTRY['largeop']);
        self::assertFalse(OperatorDictionary::DEFAULT_ENTRY['movablelimits']);
    }

    public function testDefaultEntryStructure(): void
    {
        // The DEFAULT_ENTRY contract: zero spacing, non-stretchy.
        // Documents what unrecognised operators get.
        self::assertSame(0.0, OperatorDictionary::DEFAULT_ENTRY['lspace']);
        self::assertSame(0.0, OperatorDictionary::DEFAULT_ENTRY['rspace']);
        self::assertFalse(OperatorDictionary::DEFAULT_ENTRY['stretchy']);
    }
}
