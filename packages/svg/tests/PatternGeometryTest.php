<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Tests;

use Phpdftk\Svg\Pattern;
use PHPUnit\Framework\TestCase;

/**
 * SVG 2 §13.3 — `<pattern>`'s `x` / `y` / `width` / `height` are
 * lengths. In the default `objectBoundingBox` mode a percentage is a
 * FRACTION of the bounding box, so `100%` is one bounding box; in
 * `userSpaceOnUse` mode an absolute unit suffix converts to CSS px.
 *
 * All four accessors cast the raw attribute straight to float, so
 * `"100%"` read as 100 — a hundred bounding boxes — and `"1in"` as 1.
 */
final class PatternGeometryTest extends TestCase
{
    private function pattern(array $attributes): Pattern
    {
        $p = new Pattern();
        foreach ($attributes as $name => $value) {
            $p->setAttribute($name, (string) $value);
        }
        return $p;
    }

    public function testPercentagesNormaliseToAFraction(): void
    {
        $p = $this->pattern(['width' => '100%', 'height' => '50%', 'x' => '25%', 'y' => '10%']);
        self::assertSame(1.0, $p->width());
        self::assertSame(0.5, $p->height());
        self::assertSame(0.25, $p->x());
        self::assertSame(0.1, $p->y());
    }

    public function testUnitlessNumbersAreUnchanged(): void
    {
        $p = $this->pattern(['width' => '0.5', 'height' => '2']);
        self::assertSame(0.5, $p->width());
        self::assertSame(2.0, $p->height());
    }

    public function testAbsoluteUnitsConvertToCssPixels(): void
    {
        $p = $this->pattern(['width' => '1in', 'height' => '1pc']);
        self::assertSame(96.0, $p->width());
        self::assertSame(16.0, $p->height());
    }

    public function testAbsentAttributesAreZero(): void
    {
        // Guard: `x`/`y` default to 0, and a zero width or height
        // disables the pattern — the painter's `> 0` test depends on
        // this staying 0 rather than becoming null or NaN.
        $p = $this->pattern([]);
        self::assertSame(0.0, $p->x());
        self::assertSame(0.0, $p->y());
        self::assertSame(0.0, $p->width());
        self::assertSame(0.0, $p->height());
    }

    public function testGarbageIsZero(): void
    {
        self::assertSame(0.0, $this->pattern(['width' => 'auto'])->width());
    }
}
