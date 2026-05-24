<?php

declare(strict_types=1);

namespace Phpdftk\Text\Tests;

use Phpdftk\Text\Bidi;
use Phpdftk\Text\BidiBase;
use Phpdftk\Text\LineBreaker;
use Phpdftk\Text\LineBreakKind;
use PHPUnit\Framework\TestCase;

/**
 * Smoke test for the text-pipeline pieces working together. Each piece is
 * unit-tested in its own file; this checks that the byte-offset conventions
 * line up so the layout engine in Phase 1F can consume them in tandem.
 */
final class PipelineSmokeTest extends TestCase
{
    public function testLatinPipeline(): void
    {
        $text = "Hello world,\nhow are you?";

        $lineBreaker = new LineBreaker();
        $bidi = new Bidi();

        $breaks = iterator_to_array($lineBreaker->breakOpportunities($text), false);
        $bidiResult = $bidi->analyze($text);

        // One mandatory break at the \n (offset 13).
        $mandatoryOffsets = [];
        foreach ($breaks as $b) {
            if ($b->kind === LineBreakKind::Mandatory) {
                $mandatoryOffsets[] = $b->offset;
            }
        }
        self::assertContains(13, $mandatoryOffsets);

        // All LTR — single bidi run covering the whole string.
        self::assertSame(BidiBase::Ltr, $bidiResult->resolvedBase);
        self::assertCount(1, $bidiResult->runs);
        self::assertSame(strlen($text), $bidiResult->runs[0]->length);
    }

    public function testMixedScriptPipeline(): void
    {
        // English then Hebrew then English.
        $text = "Hi \u{05E9}\u{05DC}\u{05D5}\u{05DD} all";

        $bidi = new Bidi();
        $result = $bidi->analyze($text);

        // Should produce at least two runs at distinct levels.
        $levels = array_unique(array_map(static fn($r) => $r->level, $result->runs));
        self::assertGreaterThan(1, count($levels), 'mixed-script text should produce multiple level runs');

        // Bidi result + line-break offsets are independent — verify they still
        // refer to the same byte positions.
        $breaks = iterator_to_array((new LineBreaker())->breakOpportunities($text), false);
        $byteLength = strlen($text);
        foreach ($breaks as $b) {
            self::assertGreaterThanOrEqual(0, $b->offset);
            self::assertLessThanOrEqual($byteLength, $b->offset);
        }
        foreach ($result->runs as $r) {
            self::assertLessThanOrEqual($byteLength, $r->offset + $r->length);
        }
    }
}
