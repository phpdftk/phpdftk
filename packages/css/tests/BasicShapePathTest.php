<?php

declare(strict_types=1);

namespace Phpdftk\Css\Tests;

use Phpdftk\Css\Shape\BasicShapePath;
use Phpdftk\Css\Value\PolygonShape;
use Phpdftk\Css\ValueParser;
use PHPUnit\Framework\TestCase;

/**
 * CSS Shapes 1 §3 — resolving a `<basic-shape>` against a reference box
 * into a flat, y-down outline. Shared by the HTML painter's `clip-path`
 * and the SVG translator's, so the geometry only has to be right once.
 */
final class BasicShapePathTest extends TestCase
{
    private ValueParser $parser;

    protected function setUp(): void
    {
        $this->parser = new ValueParser();
    }

    /**
     * @return array{fillRule: 'nonzero'|'evenodd', commands: list<list<string|float>>}|null
     */
    private function build(string $css, float $x, float $y, float $w, float $h): ?array
    {
        return BasicShapePath::build($this->parser->parseFromString($css), $x, $y, $w, $h);
    }

    // -- negatives ---------------------------------------------------------

    public function testNonShapeValuesResolveToNoOutline(): void
    {
        // A keyword, a url() reference and a plain length are all things a
        // `clip-path` can hold that are NOT basic shapes. Each must come
        // back as null so the caller leaves the element unclipped instead
        // of clipping it to an empty region.
        foreach (['none', 'border-box', 'url(#clip)', '10px'] as $css) {
            self::assertNull(
                $this->build($css, 0, 0, 100, 100),
                "$css is not a basic shape",
            );
        }
    }

    public function testPolygonWithFewerThanThreeVerticesResolvesToNoOutline(): void
    {
        // Two vertices enclose no area. Returning an outline here would
        // clip the element away entirely; returning null leaves it whole.
        foreach (['polygon(0% 0%)', 'polygon(0% 0%, 100% 0%)'] as $css) {
            self::assertNull($this->build($css, 0, 0, 100, 100), $css);
        }
    }

    public function testUnknownBasicShapeSubclassResolvesToNoOutline(): void
    {
        // A `<basic-shape>` the resolver does not model (a shape value
        // with no branch) must not silently produce an empty outline.
        $shape = new readonly class extends \Phpdftk\Css\Value\BasicShape {
            public function toCss(): string
            {
                return 'made-up()';
            }
        };
        self::assertNull(BasicShapePath::build($shape, 0, 0, 100, 100));
    }

    public function testZeroSizedInsetCollapsesToAnEmptyRectangleNotANegativeOne(): void
    {
        // Insets larger than the box would give a negative extent; the
        // outline must clamp to zero so the emitted path is degenerate
        // rather than inside-out.
        $outline = $this->build('inset(80px)', 0, 0, 100, 100);
        self::assertNotNull($outline);
        // M(80,80) L(20,80) ... — width clamped to 0 leaves x2 == x1.
        self::assertSame(['M', 80.0, 80.0], $outline['commands'][0]);
        self::assertSame(['L', 80.0, 80.0], $outline['commands'][1]);
    }

    public function testDegenerateReferenceBoxStillProducesAFiniteOutline(): void
    {
        $outline = $this->build('circle(50%)', 0, 0, 0, 0);
        self::assertNotNull($outline);
        foreach ($outline['commands'] as $command) {
            foreach (array_slice($command, 1) as $n) {
                self::assertIsFloat($n);
                self::assertTrue(is_finite($n), 'no NaN/INF from a zero-sized box');
            }
        }
    }

    // -- positives ---------------------------------------------------------

    public function testCirclePercentageRadiusUsesTheDiagonalOverRootTwo(): void
    {
        // CSS Shapes 1 §3.2 — a percentage radius resolves against
        // sqrt(w² + h²) / √2, which for a square is just the side.
        $outline = $this->build('circle(50%)', 0, 0, 100, 100);
        self::assertNotNull($outline);
        // The outline starts at (cx + r, cy) = (50 + 50, 50).
        self::assertSame(['M', 100.0, 50.0], $outline['commands'][0]);
    }

    public function testCircleExtentKeywordsMeasureFromTheResolvedCentre(): void
    {
        $outline = $this->build('circle(farthest-side at 25% 50%)', 0, 0, 100, 100);
        self::assertNotNull($outline);
        // centre (25, 50); farthest side is the right edge, 75 away.
        self::assertSame(['M', 100.0, 50.0], $outline['commands'][0]);
    }

    public function testCalcPositionsResolveAgainstTheReferenceBox(): void
    {
        // `calc(50% - 10px)` of a 200px axis is 90px — the SVG geometry-box
        // reftests depend on this exact form.
        $outline = $this->build('circle(25% at calc(50% - 10px) calc(50% - 10px))', 0, 0, 200, 200);
        self::assertNotNull($outline);
        self::assertSame(['M', 140.0, 90.0], $outline['commands'][0]);
    }

    public function testEllipseRadiiResolveAgainstTheirOwnAxis(): void
    {
        // Unlike circle(), an ellipse percentage radius uses the matching
        // axis extent, not the diagonal.
        $outline = $this->build('ellipse(50% 25%)', 0, 0, 200, 100);
        self::assertNotNull($outline);
        // centre (100, 50), rx = 100, ry = 25 → start at (200, 50).
        self::assertSame(['M', 200.0, 50.0], $outline['commands'][0]);
    }

    public function testReferenceBoxOriginOffsetsEveryCoordinate(): void
    {
        $outline = $this->build('inset(10px)', 30, 40, 100, 100);
        self::assertNotNull($outline);
        self::assertSame(['M', 40.0, 50.0], $outline['commands'][0]);
        self::assertSame(['L', 120.0, 50.0], $outline['commands'][1]);
    }

    public function testPolygonKeepsItsFillRuleAndClosesTheSubpath(): void
    {
        $outline = $this->build('polygon(evenodd, 0% 0%, 100% 0%, 50% 100%)', 0, 0, 100, 100);
        self::assertNotNull($outline);
        self::assertSame('evenodd', $outline['fillRule']);
        self::assertSame(['M', 0.0, 0.0], $outline['commands'][0]);
        self::assertSame(['Z'], $outline['commands'][3]);
    }

    public function testPolygonDefaultsToNonzero(): void
    {
        $outline = new PolygonShape('nonzero', [
            [new \Phpdftk\Css\Value\Percentage(0), new \Phpdftk\Css\Value\Percentage(0)],
            [new \Phpdftk\Css\Value\Percentage(100), new \Phpdftk\Css\Value\Percentage(0)],
            [new \Phpdftk\Css\Value\Percentage(50), new \Phpdftk\Css\Value\Percentage(100)],
        ]);
        $built = BasicShapePath::build($outline, 0, 0, 100, 100);
        self::assertNotNull($built);
        self::assertSame('nonzero', $built['fillRule']);
    }

    public function testRectEdgesAreAbsolutePositionsWithAutoFallingBackToTheBoxEdge(): void
    {
        // CSS Shapes 2 §4.5 — rect(top right bottom left); `auto` on the
        // right edge means the box's right edge, not "no inset".
        $outline = $this->build('rect(10px auto 90px 20px)', 0, 0, 100, 100);
        self::assertNotNull($outline);
        self::assertSame(['M', 20.0, 10.0], $outline['commands'][0]);
        self::assertSame(['L', 100.0, 10.0], $outline['commands'][1]);
        self::assertSame(['L', 100.0, 90.0], $outline['commands'][2]);
    }

    public function testXywhIsAnOriginPlusSize(): void
    {
        $outline = $this->build('xywh(10% 20% 50% 50%)', 0, 0, 100, 100);
        self::assertNotNull($outline);
        self::assertSame(['M', 10.0, 20.0], $outline['commands'][0]);
        self::assertSame(['L', 60.0, 20.0], $outline['commands'][1]);
    }

    public function testEllipseOutlineIsFourCubicsAndACloseCommand(): void
    {
        $outline = $this->build('circle(40px)', 0, 0, 100, 100);
        self::assertNotNull($outline);
        $kinds = array_map(static fn(array $c): string => (string) $c[0], $outline['commands']);
        self::assertSame(['M', 'C', 'C', 'C', 'C', 'Z'], $kinds);
    }
}
