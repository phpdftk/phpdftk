<?php

declare(strict_types=1);

namespace Phpdftk\SvgToPdf\Tests\Geometry;

use Phpdftk\Svg\Parser as SvgParser;
use Phpdftk\Svg\Element;
use Phpdftk\SvgToPdf\Geometry\BoundingBox;
use PHPUnit\Framework\TestCase;

/**
 * SVG 2 §7.10 — a PERCENTAGE geometry attribute resolves against the
 * viewport, and the object bounding box has to be measured with that
 * resolution applied.
 *
 * The shape accessors strip the `%` and read `width="100%"` as a
 * hundred user units, which silently mis-sized the box every
 * `objectBoundingBox` gradient, pattern, mask and clip resolves
 * against. The symptom that surfaced it was
 * `svg/pservers/reftests/pattern-opacity-01`: a page-sized `<rect
 * width="100%" height="100%">` got a 100x100 tile and painted a
 * hundred-pixel corner of a page-sized fill.
 */
final class BoundingBoxPercentageTest extends TestCase
{
    private function shape(string $markup): Element
    {
        $doc = (new SvgParser())->parse(
            '<svg xmlns="http://www.w3.org/2000/svg" width="400" height="300">'
            . $markup . '</svg>',
        );
        foreach ($doc->children as $child) {
            if ($child instanceof Element) {
                return $child;
            }
        }
        self::fail('no shape parsed');
    }

    private const array VIEWPORT = ['w' => 400.0, 'h' => 300.0];

    // ---------------------------------------------------------------
    // Negative cases — the readings that were wrong.
    // ---------------------------------------------------------------

    /** Without a viewport the old reading stands, so callers that have none are unchanged. */
    public function testWithoutAViewportThePercentIsLeftToTheAccessor(): void
    {
        $box = BoundingBox::compute($this->shape('<rect width="100%" height="100%"/>'));
        self::assertNotNull($box);
        self::assertSame(100.0, $box['width']);
        self::assertSame(100.0, $box['height']);
    }

    /** A plain number is not a percentage and must not be scaled. */
    public function testPlainNumbersAreUntouched(): void
    {
        $box = BoundingBox::compute(
            $this->shape('<rect width="50" height="25"/>'),
            viewport: self::VIEWPORT,
        );
        self::assertNotNull($box);
        self::assertSame(50.0, $box['width']);
        self::assertSame(25.0, $box['height']);
    }

    /** Absolute units keep going through the accessor's conversion. */
    public function testAbsoluteUnitsAreUntouched(): void
    {
        $box = BoundingBox::compute(
            $this->shape('<rect width="1in" height="1in"/>'),
            viewport: self::VIEWPORT,
        );
        self::assertNotNull($box);
        self::assertSame(96.0, $box['width']);
    }

    /** A zero-percent width still collapses the box to nothing. */
    public function testZeroPercentGivesNoBox(): void
    {
        self::assertNull(BoundingBox::compute(
            $this->shape('<rect width="0%" height="50%"/>'),
            viewport: self::VIEWPORT,
        ));
    }

    // ---------------------------------------------------------------
    // Positive cases.
    // ---------------------------------------------------------------

    public function testRectPercentagesResolvePerAxis(): void
    {
        $box = BoundingBox::compute(
            $this->shape('<rect x="10%" y="10%" width="50%" height="50%"/>'),
            viewport: self::VIEWPORT,
        );
        self::assertNotNull($box);
        self::assertSame(40.0, $box['minX']);
        self::assertSame(30.0, $box['minY']);
        self::assertSame(200.0, $box['width']);
        self::assertSame(150.0, $box['height']);
    }

    /** `r` is tied to neither axis, so it uses the normalized diagonal. */
    public function testCircleRadiusUsesTheNormalizedDiagonal(): void
    {
        $box = BoundingBox::compute(
            $this->shape('<circle cx="50%" cy="50%" r="10%"/>'),
            viewport: self::VIEWPORT,
        );
        self::assertNotNull($box);
        $r = sqrt(400.0 ** 2 + 300.0 ** 2) / M_SQRT2 * 0.1;
        self::assertEqualsWithDelta(2.0 * $r, $box['width'], 1.0e-9);
        self::assertEqualsWithDelta(200.0 - $r, $box['minX'], 1.0e-9);
        self::assertEqualsWithDelta(150.0 - $r, $box['minY'], 1.0e-9);
    }

    public function testEllipseRadiiResolvePerAxis(): void
    {
        $box = BoundingBox::compute(
            $this->shape('<ellipse cx="50%" cy="50%" rx="25%" ry="50%"/>'),
            viewport: self::VIEWPORT,
        );
        self::assertNotNull($box);
        self::assertSame(200.0, $box['width']);
        self::assertSame(300.0, $box['height']);
    }

    /** The stroke box grows from the RESOLVED fill box, not the raw one. */
    public function testStrokeBoxGrowsFromTheResolvedBox(): void
    {
        $box = BoundingBox::compute(
            $this->shape('<rect width="50%" height="50%" stroke="#000" stroke-width="10"/>'),
            includeStroke: true,
            viewport: self::VIEWPORT,
        );
        self::assertNotNull($box);
        self::assertSame(210.0, $box['width']);
        self::assertSame(160.0, $box['height']);
        self::assertSame(-5.0, $box['minX']);
    }
}
