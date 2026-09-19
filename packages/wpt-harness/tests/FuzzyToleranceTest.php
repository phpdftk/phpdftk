<?php

declare(strict_types=1);

namespace Phpdftk\WptHarness\Tests;

use Phpdftk\WptHarness\FuzzyTolerance;
use PHPUnit\Framework\TestCase;

/**
 * `<meta name=fuzzy>` grammar and comparison semantics, pinned to
 * WPT's own implementation — `tools/manifest/sourcefile.py::fuzzy`
 * for the grammar, `tools/wptrunner/wptrunner/executors/base.py`
 * for the pass decision.
 */
final class FuzzyToleranceTest extends TestCase
{
    // ------------------------------------------------------------------
    // Grammar
    // ------------------------------------------------------------------

    public function testParsesBothNamedTolerances(): void
    {
        $fuzzy = FuzzyTolerance::parse('maxDifference=0-5;totalPixels=0-100');

        self::assertNotNull($fuzzy);
        self::assertSame([0, 5], $fuzzy->maxDifference);
        self::assertSame([0, 100], $fuzzy->totalPixels);
    }

    public function testNamedTolerancesMayAppearInEitherOrder(): void
    {
        // 22 corpus fixtures write totalPixels first.
        $fuzzy = FuzzyTolerance::parse('totalPixels=0-2;maxDifference=0-1');

        self::assertNotNull($fuzzy);
        self::assertSame([0, 1], $fuzzy->maxDifference);
        self::assertSame([0, 2], $fuzzy->totalPixels);
    }

    public function testToleratesWhitespaceAroundNamesAndRanges(): void
    {
        $fuzzy = FuzzyTolerance::parse('maxDifference=0-4; totalPixels = 0 - 33000');

        self::assertNotNull($fuzzy);
        self::assertSame([0, 4], $fuzzy->maxDifference);
        self::assertSame([0, 33000], $fuzzy->totalPixels);
    }

    public function testPositionalShorthandIsMaxDifferenceThenTotalPixels(): void
    {
        $fuzzy = FuzzyTolerance::parse('0-120;0-10');

        self::assertNotNull($fuzzy);
        self::assertSame([0, 120], $fuzzy->maxDifference, 'first positional range is maxDifference');
        self::assertSame([0, 10], $fuzzy->totalPixels);
    }

    public function testBareNumberIsADegenerateRange(): void
    {
        // WPT: `range_min = range_max = range_str_value`.
        $fuzzy = FuzzyTolerance::parse('maxDifference=1;totalPixels=0-5000');

        self::assertNotNull($fuzzy);
        self::assertSame([1, 1], $fuzzy->maxDifference);
    }

    public function testMixedNamedAndPositionalFillsTheRemainingSlot(): void
    {
        $fuzzy = FuzzyTolerance::parse('totalPixels=0-40;0-3');

        self::assertNotNull($fuzzy);
        self::assertSame([0, 3], $fuzzy->maxDifference);
        self::assertSame([0, 40], $fuzzy->totalPixels);
    }

    public function testNonZeroLowerBoundsArePreserved(): void
    {
        $fuzzy = FuzzyTolerance::parse('maxDifference=0-1; totalPixels=7600-8700');

        self::assertNotNull($fuzzy);
        self::assertSame([7600, 8700], $fuzzy->totalPixels);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformedValues(): iterable
    {
        yield 'template placeholder' => ['{{ fuzzy }}'];
        yield 'single range only' => ['maxDifference=0-5'];
        yield 'three ranges' => ['0-1;0-2;0-3'];
        yield 'unknown property' => ['maxDiff=0-5;totalPixels=0-100'];
        yield 'non-numeric bound' => ['maxDifference=0-x;totalPixels=0-100'];
        yield 'empty' => [''];
        yield 'duplicate property' => ['maxDifference=0-1;maxDifference=0-2'];
        yield 'negative bound' => ['maxDifference=-5-5;totalPixels=0-100'];
    }

    public function testMalformedValuesAreRejectedRatherThanGuessedAt(): void
    {
        foreach (self::malformedValues() as $label => [$content]) {
            self::assertNull(FuzzyTolerance::parse($content), $label);
        }
    }

    // ------------------------------------------------------------------
    // Comparison semantics
    // ------------------------------------------------------------------

    public function testWithinBothTolerancesPasses(): void
    {
        $fuzzy = FuzzyTolerance::parse('maxDifference=0-5;totalPixels=0-100');
        self::assertNotNull($fuzzy);

        self::assertTrue($fuzzy->permits(maxPerChannel: 5, pixelsDifferent: 100));
        self::assertTrue($fuzzy->permits(maxPerChannel: 1, pixelsDifferent: 1));
    }

    public function testColourDifferenceBeyondMaxDifferenceFails(): void
    {
        // The defect this pins: a fixture declaring a 5/255 colour
        // tolerance must NOT pass on a 200/255 difference merely
        // because only a handful of pixels moved.
        $fuzzy = FuzzyTolerance::parse('maxDifference=0-5;totalPixels=0-100');
        self::assertNotNull($fuzzy);

        self::assertFalse($fuzzy->permits(maxPerChannel: 200, pixelsDifferent: 4));
    }

    public function testTooManyDifferingPixelsFails(): void
    {
        $fuzzy = FuzzyTolerance::parse('maxDifference=0-5;totalPixels=0-100');
        self::assertNotNull($fuzzy);

        self::assertFalse($fuzzy->permits(maxPerChannel: 2, pixelsDifferent: 101));
    }

    public function testFewerDifferingPixelsThanTheDeclaredFloorFails(): void
    {
        // wptrunner enforces `allowed_different[0] <= pixels_different`.
        $fuzzy = FuzzyTolerance::parse('maxDifference=0-1;totalPixels=7600-8700');
        self::assertNotNull($fuzzy);

        self::assertFalse($fuzzy->permits(maxPerChannel: 1, pixelsDifferent: 12));
        self::assertTrue($fuzzy->permits(maxPerChannel: 1, pixelsDifferent: 8000));
    }

    public function testExactMatchPassesWhenTheFloorIsZero(): void
    {
        $fuzzy = FuzzyTolerance::parse('maxDifference=0-1;totalPixels=0-8700');
        self::assertNotNull($fuzzy);

        self::assertTrue($fuzzy->permits(maxPerChannel: 0, pixelsDifferent: 0));
    }

    public function testExactMatchPassesEvenAgainstANonZeroPixelFloor(): void
    {
        // `max_per_channel == 0 and allowed_per_channel[0] == 0` is the
        // second escape clause in wptrunner.
        $fuzzy = FuzzyTolerance::parse('maxDifference=0-1;totalPixels=7600-8700');
        self::assertNotNull($fuzzy);

        self::assertTrue($fuzzy->permits(maxPerChannel: 0, pixelsDifferent: 0));
    }

    public function testTrivialDeclarationIsRecognised(): void
    {
        $fuzzy = FuzzyTolerance::parse('0-0;0-0');
        self::assertNotNull($fuzzy);
        self::assertTrue($fuzzy->isTrivial());

        $real = FuzzyTolerance::parse('0-1;0-2');
        self::assertNotNull($real);
        self::assertFalse($real->isTrivial());
    }
}
