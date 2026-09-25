<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Tests\Gradient;

use Phpdftk\Svg\Gradient\LinearGradient;
use Phpdftk\Svg\Gradient\RadialGradient;
use PHPUnit\Framework\TestCase;

/**
 * SVG 2 §13.6.5 / §13.7.5 — gradient coordinates are `<length>`s, so
 * an absolute unit suffix is meaningful: `x2="1in"` is 96 CSS px, which
 * in `objectBoundingBox` mode means 96 TIMES the bounding-box width,
 * not one times it.
 *
 * The parser matched `number` plus an optional `%` and threw any other
 * suffix away, so `1in` read as `1`.
 */
final class GradientAbsoluteUnitsTest extends TestCase
{
    private function linear(string $attr, string $value): ?float
    {
        $g = new LinearGradient();
        $g->setAttribute($attr, $value);
        return match ($attr) {
            'x1' => $g->x1(),
            'y1' => $g->y1(),
            'x2' => $g->x2(),
            default => $g->y2(),
        };
    }

    public function testInchesResolveToCssPixels(): void
    {
        self::assertSame(96.0, $this->linear('x2', '1in'));
    }

    public function testEveryAbsoluteUnitIsConverted(): void
    {
        self::assertEqualsWithDelta(72.0, $this->linear('x2', '0.75in'), 1.0e-9);
        self::assertEqualsWithDelta(96.0 / 2.54, $this->linear('x2', '1cm'), 1.0e-9);
        self::assertEqualsWithDelta(96.0 / 25.4, $this->linear('x2', '1mm'), 1.0e-9);
        self::assertEqualsWithDelta(96.0 / 72.0, $this->linear('x2', '1pt'), 1.0e-9);
        self::assertEqualsWithDelta(16.0, $this->linear('x2', '1pc'), 1.0e-9);
        self::assertEqualsWithDelta(3.0, $this->linear('x2', '3px'), 1.0e-9);
    }

    public function testPercentagesStillNormaliseToAFraction(): void
    {
        // Guard: the `%` branch is what keeps `x2="100%"` meaning "one
        // bounding-box width" instead of a hundred of them.
        self::assertSame(1.0, $this->linear('x2', '100%'));
        self::assertSame(0.5, $this->linear('x2', '50%'));
    }

    public function testUnitlessNumbersAreUnchanged(): void
    {
        self::assertSame(1.0, $this->linear('x2', '1'));
        self::assertSame(0.25, $this->linear('y2', '0.25'));
    }

    public function testRadialGradientConvertsToo(): void
    {
        $g = new RadialGradient();
        $g->setAttribute('r', '1in');
        $g->setAttribute('cx', '50%');
        self::assertSame(96.0, $g->r());
        self::assertSame(0.5, $g->cx());
    }

    public function testAnUnknownUnitIsNotScaled(): void
    {
        // Guard: only the ABSOLUTE units convert here. A font-relative
        // one needs context this accessor doesn't have, so it must pass
        // its numeric part through rather than be silently multiplied.
        self::assertSame(2.0, $this->linear('x2', '2em'));
    }
}
