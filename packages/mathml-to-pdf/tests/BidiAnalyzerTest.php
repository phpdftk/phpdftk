<?php

declare(strict_types=1);

namespace Phpdftk\MathmlToPdf\Tests;

use Phpdftk\MathmlToPdf\BidiAnalyzer;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the bidi-class analyzer that the painter consults
 * when deciding whether token content needs UAX #9 reordering.
 *
 * Tests cover the per-codepoint classification, the paragraph-
 * direction inference (first strong character wins), and the run-
 * direction categorisation (pure LTR / pure RTL / mixed / neutral).
 */
final class BidiAnalyzerTest extends TestCase
{
    public function testLatinLetterIsLtr(): void
    {
        self::assertSame(
            BidiAnalyzer::DIRECTION_LTR,
            BidiAnalyzer::directionOf(ord('x')),
        );
    }

    public function testHebrewLetterIsRtl(): void
    {
        // U+05D0 HEBREW LETTER ALEF.
        self::assertSame(
            BidiAnalyzer::DIRECTION_RTL,
            BidiAnalyzer::directionOf(0x05D0),
        );
    }

    public function testArabicLetterIsRtl(): void
    {
        // U+0627 ARABIC LETTER ALEF — classified AL (Right-to-Left
        // Arabic) but reports as RTL in the simplified surface.
        self::assertSame(
            BidiAnalyzer::DIRECTION_RTL,
            BidiAnalyzer::directionOf(0x0627),
        );
    }

    public function testDigitsHaveNoStrongDirection(): void
    {
        foreach (['0', '5', '9'] as $digit) {
            self::assertNull(
                BidiAnalyzer::directionOf(ord($digit)),
            );
        }
    }

    public function testCommonPunctuationIsNeutral(): void
    {
        foreach ([' ', '.', ',', '!', '?'] as $p) {
            self::assertNull(
                BidiAnalyzer::directionOf(ord($p)),
            );
        }
    }

    public function testParagraphDirectionFromLatinFirstCharacter(): void
    {
        self::assertSame(
            BidiAnalyzer::DIRECTION_LTR,
            BidiAnalyzer::paragraphDirection('hello'),
        );
    }

    public function testParagraphDirectionFromHebrewFirstCharacter(): void
    {
        // 'שלום' = U+05E9 U+05DC U+05D5 U+05DD.
        self::assertSame(
            BidiAnalyzer::DIRECTION_RTL,
            BidiAnalyzer::paragraphDirection("\u{05E9}\u{05DC}\u{05D5}\u{05DD}"),
        );
    }

    public function testParagraphDirectionSkipsLeadingNeutrals(): void
    {
        // Leading digits + punctuation, then a strong-LTR char.
        self::assertSame(
            BidiAnalyzer::DIRECTION_LTR,
            BidiAnalyzer::paragraphDirection('  123 x'),
        );
    }

    public function testParagraphDirectionFallbackWhenNoStrong(): void
    {
        self::assertSame(
            BidiAnalyzer::DIRECTION_LTR,
            BidiAnalyzer::paragraphDirection('  123 '),
        );
        self::assertSame(
            BidiAnalyzer::DIRECTION_RTL,
            BidiAnalyzer::paragraphDirection(
                '  123 ',
                fallback: BidiAnalyzer::DIRECTION_RTL,
            ),
        );
    }

    public function testRunDirectionPureLtr(): void
    {
        self::assertSame(
            BidiAnalyzer::DIRECTION_LTR,
            BidiAnalyzer::runDirection('hello world'),
        );
    }

    public function testRunDirectionPureRtl(): void
    {
        self::assertSame(
            BidiAnalyzer::DIRECTION_RTL,
            BidiAnalyzer::runDirection("\u{05E9}\u{05DC}\u{05D5}\u{05DD}"),
        );
    }

    public function testRunDirectionMixed(): void
    {
        // 'hello' + Hebrew + 'world' triggers mixed.
        self::assertSame(
            'mixed',
            BidiAnalyzer::runDirection('hello ' . "\u{05E9}" . ' world'),
        );
    }

    public function testRunDirectionNeutralForPureWhitespacePunctuation(): void
    {
        self::assertSame(
            BidiAnalyzer::DIRECTION_NEUTRAL,
            BidiAnalyzer::runDirection('  . , !'),
        );
    }

    public function testRunDirectionDigitsOnlyReturnsLtr(): void
    {
        // Numbers display LTR even in RTL paragraphs per UAX #9 -
        // a digit-only run reports DIRECTION_LTR.
        self::assertSame(
            BidiAnalyzer::DIRECTION_LTR,
            BidiAnalyzer::runDirection('1234'),
        );
    }

    public function testIsMixedFlagMatchesRunDirection(): void
    {
        self::assertFalse(BidiAnalyzer::isMixed('hello'));
        self::assertFalse(BidiAnalyzer::isMixed("\u{05E9}\u{05DC}"));
        self::assertTrue(BidiAnalyzer::isMixed("hello \u{05E9}"));
        self::assertFalse(BidiAnalyzer::isMixed('   '));
        self::assertFalse(BidiAnalyzer::isMixed('123'));
    }
}
