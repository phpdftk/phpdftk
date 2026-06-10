<?php

declare(strict_types=1);

namespace Phpdftk\MathmlToPdf\Tests;

use Phpdftk\MathmlToPdf\BidiAnalyzer;
use Phpdftk\MathmlToPdf\BidiReorder;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the simplified UAX #9 reordering pass.
 *
 * Covers the common cases the painter actually hits:
 *
 *   - LTR paragraph with embedded Hebrew/Arabic run.
 *   - RTL paragraph with embedded Latin run.
 *   - Pure-direction inputs (no reordering needed).
 *   - Neutrals attaching to surrounding direction.
 *   - Trailing/leading whitespace handling.
 */
final class BidiReorderTest extends TestCase
{
    public function testPureLtrPassesThrough(): void
    {
        self::assertSame('hello', BidiReorder::reorder('hello'));
    }

    public function testPureRtlPassesThroughUnchanged(): void
    {
        // Pure-RTL content is handled by emitText's separate path -
        // reorder() returns it unchanged because runDirection != mixed.
        $hebrew = "\u{05D0}\u{05D1}\u{05D2}";
        self::assertSame($hebrew, BidiReorder::reorder($hebrew));
    }

    public function testMixedLtrParagraphReversesEmbeddedRtl(): void
    {
        // 'hello אבג world' - the Hebrew run reverses in place.
        $input = 'hello ' . "\u{05D0}\u{05D1}\u{05D2}" . ' world';
        $expected = 'hello '
            . "\u{05D2}\u{05D1}\u{05D0}"
            . ' world';
        self::assertSame($expected, BidiReorder::reorder($input));
    }

    public function testMixedRtlParagraphReversesRunOrder(): void
    {
        // 'אבג hello גדה' under RTL paragraph: in visual order, the
        // last RTL run appears LEFTMOST. Internal LTR run keeps its
        // text but its position shifts to between the now-swapped
        // RTL runs (which are also reversed internally).
        //
        // Input runs:
        //   1: 'אבג ' (RTL)
        //   2: 'hello' (LTR)
        //   3: ' גדה' (RTL)
        //
        // Visual order under RTL paragraph (right-to-left runs):
        //   reversed run order: 3, 2, 1
        //   each RTL reversed internally; LTR kept as-is.
        //
        // So output starts with reverseUtf8(' גדה') = 'הדג '
        $input = "\u{05D0}\u{05D1}\u{05D2}" . ' hello '
            . "\u{05D3}\u{05D4}\u{05D5}";
        $output = BidiReorder::reorder($input, BidiAnalyzer::DIRECTION_RTL);
        // First three codepoints should be the reversed last
        // RTL run: U+05D5 U+05D4 U+05D3.
        self::assertSame(
            "\u{05D5}\u{05D4}\u{05D3}",
            mb_substr($output, 0, 3),
        );
    }

    public function testNeutralsBetweenRunsStayInPlace(): void
    {
        // 'hello, אבג!' - punctuation forms its own neutral runs,
        // so the RTL run reverses in isolation and the surrounding
        // ',' / ' ' / '!' keep their positions.
        $input = 'hello, ' . "\u{05D0}\u{05D1}\u{05D2}" . '!';
        $expected = 'hello, ' . "\u{05D2}\u{05D1}\u{05D0}" . '!';
        self::assertSame($expected, BidiReorder::reorder($input));
    }

    public function testReorderRoundTripsForSingleDirection(): void
    {
        // For non-mixed input, reorder() short-circuits to identity.
        self::assertSame('1234', BidiReorder::reorder('1234'));
        self::assertSame('  ,  ', BidiReorder::reorder('  ,  '));
    }
}
