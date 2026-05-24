<?php

declare(strict_types=1);

namespace Phpdftk\Text\Tests;

use Phpdftk\Text\LineBreaker;
use Phpdftk\Text\LineBreakKind;
use Phpdftk\Text\LineBreakOpportunity;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the UAX #14 line breaker. These spot-check ICU's reported
 * boundaries against well-known cases. We don't try to assert the full UAX
 * #14 conformance suite (vendored as part of `phpdftk/text` Phase 2) — at
 * minimum these confirm the wrapper produces sensible offsets and
 * classifies hard breaks correctly.
 */
final class LineBreakerTest extends TestCase
{
    private LineBreaker $breaker;

    protected function setUp(): void
    {
        $this->breaker = new LineBreaker();
    }

    /** @return list<LineBreakOpportunity> */
    private function opps(string $text, string $locale = 'en'): array
    {
        return iterator_to_array($this->breaker->breakOpportunities($text, $locale), false);
    }

    public function testEmptyStringYieldsNoOpportunities(): void
    {
        self::assertSame([], $this->opps(''));
    }

    public function testSimpleAsciiText(): void
    {
        // "Hello world" — break opportunity after the space (position 6),
        // and one at end (position 11).
        $opps = $this->opps('Hello world');
        $offsets = array_map(static fn(LineBreakOpportunity $o): int => $o->offset, $opps);
        self::assertContains(6, $offsets, 'break after space');
        self::assertContains(11, $offsets, 'break at end');
    }

    public function testHardBreakAtNewline(): void
    {
        $opps = $this->opps("Hello\nworld");
        $newlineOpp = null;
        foreach ($opps as $o) {
            if ($o->offset === 6) {
                $newlineOpp = $o;
                break;
            }
        }
        self::assertNotNull($newlineOpp, 'should report break at offset 6 (after \n)');
        self::assertSame(LineBreakKind::Mandatory, $newlineOpp->kind);
    }

    public function testCrlfHardBreak(): void
    {
        $opps = $this->opps("Line1\r\nLine2");
        $found = false;
        foreach ($opps as $o) {
            if ($o->kind === LineBreakKind::Mandatory && $o->offset === 7) {
                $found = true;
                break;
            }
        }
        self::assertTrue($found, 'CRLF should be a single mandatory break at offset 7');
    }

    public function testParagraphSeparatorIsMandatory(): void
    {
        // U+2029 PARAGRAPH SEPARATOR
        $opps = $this->opps("A\u{2029}B");
        $mandatoryCount = 0;
        foreach ($opps as $o) {
            if ($o->kind === LineBreakKind::Mandatory) {
                $mandatoryCount++;
            }
        }
        self::assertGreaterThanOrEqual(1, $mandatoryCount);
    }

    public function testHyphenAllowsBreak(): void
    {
        // Break after the hyphen in 'mother-in-law'.
        $opps = $this->opps('mother-in-law');
        $offsets = array_map(static fn(LineBreakOpportunity $o): int => $o->offset, $opps);
        self::assertContains(7, $offsets, 'allowed break after first hyphen');
        self::assertContains(10, $offsets, 'allowed break after second hyphen');
    }

    public function testJapaneseLocaleProducesOpportunities(): void
    {
        // Japanese has many break opportunities between characters (no spaces).
        // Just verify the iterator produces multiple opportunities for a
        // multi-character Japanese string.
        $opps = $this->opps('こんにちは世界', 'ja');
        self::assertNotEmpty($opps);
        // Roughly: one break opportunity per character boundary.
        self::assertGreaterThan(1, count($opps));
    }

    public function testMultipleSoftBreaksInSentence(): void
    {
        $opps = $this->opps('The quick brown fox jumps');
        $softCount = 0;
        foreach ($opps as $o) {
            if ($o->kind === LineBreakKind::Allowed) {
                $softCount++;
            }
        }
        // At least one soft break after each space — 4 spaces minimum.
        self::assertGreaterThanOrEqual(4, $softCount);
    }
}
