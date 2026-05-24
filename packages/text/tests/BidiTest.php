<?php

declare(strict_types=1);

namespace Phpdftk\Text\Tests;

use Phpdftk\Text\Bidi;
use Phpdftk\Text\BidiBase;
use PHPUnit\Framework\TestCase;

final class BidiTest extends TestCase
{
    private Bidi $bidi;

    protected function setUp(): void
    {
        $this->bidi = new Bidi();
    }

    public function testEmptyStringResolvesToLtr(): void
    {
        $result = $this->bidi->analyze('');
        self::assertSame(BidiBase::Ltr, $result->resolvedBase);
        self::assertSame([], $result->runs);
    }

    public function testPureLtrText(): void
    {
        $result = $this->bidi->analyze('Hello world');
        self::assertSame(BidiBase::Ltr, $result->resolvedBase);
        self::assertCount(1, $result->runs);
        self::assertSame(0, $result->runs[0]->level);
        self::assertSame(0, $result->runs[0]->offset);
        self::assertSame(11, $result->runs[0]->length);
    }

    public function testPureRtlText(): void
    {
        // Hebrew word: שלום
        $text = "\u{05E9}\u{05DC}\u{05D5}\u{05DD}";
        $result = $this->bidi->analyze($text);
        self::assertSame(BidiBase::Rtl, $result->resolvedBase);
        self::assertCount(1, $result->runs);
        self::assertSame(1, $result->runs[0]->level);
    }

    public function testAutoDetectionPicksFirstStrong(): void
    {
        // Leading whitespace then strong LTR — base should be LTR.
        $result = $this->bidi->analyze('  Hello');
        self::assertSame(BidiBase::Ltr, $result->resolvedBase);

        // Leading whitespace then Arabic — base should be RTL.
        $rtl = "  \u{0633}\u{0644}\u{0627}\u{0645}"; // "  سلام"
        $result = $this->bidi->analyze($rtl);
        self::assertSame(BidiBase::Rtl, $result->resolvedBase);
    }

    public function testExplicitBaseOverridesContent(): void
    {
        $result = $this->bidi->analyze('Hello', BidiBase::Rtl);
        self::assertSame(BidiBase::Rtl, $result->resolvedBase);
        // The L characters still get level 0; the base is just a paragraph hint.
        self::assertSame(0, $result->runs[0]->level);
    }

    public function testMixedScriptProducesMultipleRuns(): void
    {
        // "Hello שלום world"
        $text = "Hello \u{05E9}\u{05DC}\u{05D5}\u{05DD} world";
        $result = $this->bidi->analyze($text);
        self::assertGreaterThan(1, count($result->runs));
        $levels = array_map(static fn($r) => $r->level, $result->runs);
        self::assertContains(0, $levels);
        self::assertContains(1, $result->runs[0]->level === 0 ? $levels : $levels);
    }

    public function testCharLevelAtFindsRun(): void
    {
        $result = $this->bidi->analyze('Hello');
        self::assertSame(0, $result->charLevelAt(0));
        self::assertSame(0, $result->charLevelAt(4));
        self::assertNull($result->charLevelAt(100), 'out of range returns null');
    }

    public function testRunOffsetsCoverString(): void
    {
        $text = 'Hello world!';
        $result = $this->bidi->analyze($text);
        $total = 0;
        foreach ($result->runs as $r) {
            $total += $r->length;
        }
        self::assertSame(strlen($text), $total, 'runs should cover the whole input');
    }

    public function testNeutralBetweenSameDirectionAdopts(): void
    {
        // "Hi! Bye" — all LTR, space and exclamation neutral but surrounded by L.
        $result = $this->bidi->analyze('Hi! Bye');
        self::assertCount(1, $result->runs, 'neutrals between LTR fuse into one run');
        self::assertSame(0, $result->runs[0]->level);
    }
}
