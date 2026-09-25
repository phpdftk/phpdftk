<?php

declare(strict_types=1);

namespace Phpdftk\SvgToPdf\Tests;

use Phpdftk\Pdf\Core\Content\ContentStream;
use Phpdftk\Svg\Parser as SvgParser;
use Phpdftk\SvgToPdf\Translator;
use PHPUnit\Framework\TestCase;

/**
 * SVG 2 §10.4 — `<rect>` `rx` / `ry` round the corners. The model has
 * had the accessors (with the `auto` mirroring rule) since the shape
 * package landed, but the painter emitted a plain PDF `re` and never
 * read them, so every rounded rectangle drew square.
 *
 * PDF has no rounded-rect operator, so a rounded rect lowers to four
 * line segments joined by four Bézier corner arcs — meaning the `re`
 * operator must disappear entirely when the corners are rounded.
 */
final class RoundedRectTest extends TestCase
{
    private function paint(string $body): string
    {
        $doc = (new SvgParser())->parse(
            '<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100">'
            . $body . '</svg>',
        );
        $stream = new ContentStream();
        (new Translator())->paint($doc, $stream);
        return implode("\n", $stream->getOperators());
    }

    public function testSquareCornersStillUseTheRectangleOperator(): void
    {
        // Guard: the plain path must stay a single `re`. Lowering every
        // rectangle to Béziers would bloat every content stream we emit.
        $ops = $this->paint('<rect x="10" y="10" width="50" height="50" fill="blue"/>');
        self::assertStringContainsString('10 10 50 50 re', $ops);
        self::assertStringNotContainsString(' c', $ops);
    }

    public function testRoundedCornersEmitFourCurves(): void
    {
        $ops = $this->paint(
            '<rect x="10" y="10" width="50" height="50" rx="8" ry="8" fill="blue"/>',
        );
        self::assertStringNotContainsString(' re', $ops);
        self::assertSame(4, substr_count($ops, ' c'));
        // The straight edges run between the corner arcs: the top edge
        // starts at x = 10 + rx and ends at x = 10 + 50 - rx.
        self::assertStringContainsString('18 10 m', $ops);
        self::assertStringContainsString('52 10 l', $ops);
    }

    public function testASingleRadiusMirrorsToTheOtherAxis(): void
    {
        // §10.4: `rx: auto` means "use ry", and vice versa.
        $onlyRx = $this->paint('<rect width="50" height="50" rx="8" fill="blue"/>');
        $onlyRy = $this->paint('<rect width="50" height="50" ry="8" fill="blue"/>');
        self::assertSame(4, substr_count($onlyRx, ' c'));
        self::assertSame($onlyRx, $onlyRy);
    }

    public function testRadiiAreClampedToHalfTheSide(): void
    {
        // §10.4: an `rx` above half the width is reduced to half the
        // width, which turns the horizontal edges into nothing. A
        // clamp failure would send the control points outside the rect.
        $ops = $this->paint('<rect width="50" height="50" rx="40" ry="40" fill="blue"/>');
        self::assertStringContainsString('25 0 m', $ops);
        self::assertSame(4, substr_count($ops, ' c'));
    }

    public function testZeroRadiusStaysSquare(): void
    {
        // Guard: `rx="0"` is explicitly "no rounding", not a degenerate
        // arc — emitting four zero-length curves would be wasteful and
        // would change the joins under a stroke.
        $ops = $this->paint('<rect width="50" height="50" rx="0" fill="blue"/>');
        self::assertStringContainsString('0 0 50 50 re', $ops);
        self::assertStringNotContainsString(' c', $ops);
    }

    public function testNegativeRadiusIsInvalidAndIgnored(): void
    {
        $ops = $this->paint('<rect width="50" height="50" rx="-8" fill="blue"/>');
        self::assertStringContainsString('0 0 50 50 re', $ops);
    }

    public function testPercentageRadiusResolvesAgainstTheViewport(): void
    {
        // §10.1: a percentage `rx` resolves against the viewport WIDTH
        // and `ry` against its HEIGHT — not against the rect's own box.
        $ops = $this->paint('<rect width="50" height="50" rx="10%" ry="20%" fill="blue"/>');
        // rx = 10 % of 100 = 10, so the top edge starts at x = 10.
        self::assertStringContainsString('10 0 m', $ops);
        self::assertSame(4, substr_count($ops, ' c'));
    }
}
