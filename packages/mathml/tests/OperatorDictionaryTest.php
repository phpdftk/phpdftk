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

    public function testDefaultEntryStructure(): void
    {
        // The DEFAULT_ENTRY contract: zero spacing, non-stretchy.
        // Documents what unrecognised operators get.
        self::assertSame(0.0, OperatorDictionary::DEFAULT_ENTRY['lspace']);
        self::assertSame(0.0, OperatorDictionary::DEFAULT_ENTRY['rspace']);
        self::assertFalse(OperatorDictionary::DEFAULT_ENTRY['stretchy']);
    }
}
