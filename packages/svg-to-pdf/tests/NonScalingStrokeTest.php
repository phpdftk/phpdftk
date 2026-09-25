<?php

declare(strict_types=1);

namespace Phpdftk\SvgToPdf\Tests;

use Phpdftk\Pdf\Core\Content\ContentStream;
use Phpdftk\Svg\Parser as SvgParser;
use Phpdftk\SvgToPdf\SvgCascadeProjector;
use Phpdftk\SvgToPdf\Translator;
use PHPUnit\Framework\TestCase;

/**
 * SVG 2 §13.6 — `vector-effect: non-scaling-stroke`.
 *
 * The stroke is calculated in the host coordinate space rather than
 * the element's own, so its on-screen thickness must not depend on the
 * transforms between the element and the root. A PDF line width lives
 * in user space, so the transforms are cancelled by dividing the width
 * by the scale they apply.
 *
 * The effect also reaches `markerUnits="strokeWidth"` (§11.6.2), which
 * scales markers by the USED stroke width — the normalised one.
 */
final class NonScalingStrokeTest extends TestCase
{
    private function paint(string $body): string
    {
        $doc = (new SvgParser())->parse(
            '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200">'
            . $body . '</svg>',
        );
        (new SvgCascadeProjector())->project($doc);
        $stream = new ContentStream();
        (new Translator())->paint($doc, $stream);
        return implode("\n", $stream->getOperators());
    }

    // ---------------------------------------------------------------
    // Negative cases.
    // ---------------------------------------------------------------

    /** Without the effect, the width is the authored one, untouched. */
    public function testWithoutTheEffectTheWidthIsAuthored(): void
    {
        $ops = $this->paint(
            '<g transform="scale(10)"><line x2="10" stroke="#ff0000" stroke-width="10"/></g>',
        );
        self::assertStringContainsString('10 w', $ops);
        self::assertStringNotContainsString('1 w', $ops);
    }

    /** `vector-effect: none` is the initial value and changes nothing. */
    public function testExplicitNoneChangesNothing(): void
    {
        $ops = $this->paint(
            '<g transform="scale(10)">'
            . '<line x2="10" stroke="#ff0000" stroke-width="10" vector-effect="none"/></g>',
        );
        self::assertStringContainsString('10 w', $ops);
    }

    /** An unrecognised keyword is ignored, not treated as the effect. */
    public function testUnrecognisedKeywordIsIgnored(): void
    {
        $ops = $this->paint(
            '<g transform="scale(10)">'
            . '<line x2="10" stroke="#ff0000" stroke-width="10" vector-effect="bogus"/></g>',
        );
        self::assertStringContainsString('10 w', $ops);
    }

    /**
     * The property does NOT inherit, so declaring it on a `<g>` leaves
     * the shapes inside it scaling normally.
     */
    public function testTheEffectDoesNotInherit(): void
    {
        $ops = $this->paint(
            '<g transform="scale(10)" vector-effect="non-scaling-stroke">'
            . '<line x2="10" stroke="#ff0000" stroke-width="10"/></g>',
        );
        self::assertStringContainsString('10 w', $ops);
    }

    /**
     * A degenerate transform collapses the geometry to nothing; the
     * effect is skipped rather than dividing by zero.
     */
    public function testDegenerateTransformDoesNotDivideByZero(): void
    {
        $ops = $this->paint(
            '<g transform="scale(0)">'
            . '<line x2="10" stroke="#ff0000" stroke-width="10"'
            . ' vector-effect="non-scaling-stroke"/></g>',
        );
        self::assertStringContainsString('10 w', $ops);
    }

    // ---------------------------------------------------------------
    // Positive cases.
    // ---------------------------------------------------------------

    public function testTheWidthIsDividedByTheTransformScale(): void
    {
        $ops = $this->paint(
            '<g transform="scale(10)">'
            . '<line x2="10" stroke="#ff0000" stroke-width="10"'
            . ' vector-effect="non-scaling-stroke"/></g>',
        );
        self::assertStringContainsString('1 w', $ops);
    }

    /** The initial `stroke-width` is 1, and it is normalised too. */
    public function testAnAbsentStrokeWidthStillNormalises(): void
    {
        $ops = $this->paint(
            '<g transform="scale(4)">'
            . '<line x2="10" stroke="#ff0000" vector-effect="non-scaling-stroke"/></g>',
        );
        self::assertStringContainsString('0.25 w', $ops);
    }

    /** Nested transforms compound, so the whole chain is divided out. */
    public function testNestedTransformsCompound(): void
    {
        $ops = $this->paint(
            '<g transform="scale(2)"><g transform="scale(5)">'
            . '<line x2="10" stroke="#ff0000" stroke-width="10"'
            . ' vector-effect="non-scaling-stroke"/></g></g>',
        );
        self::assertStringContainsString('1 w', $ops);
    }

    /** A pure rotation scales nothing, so the width is unchanged. */
    public function testRotationLeavesTheWidthAlone(): void
    {
        $ops = $this->paint(
            '<g transform="rotate(37)">'
            . '<line x2="10" stroke="#ff0000" stroke-width="8"'
            . ' vector-effect="non-scaling-stroke"/></g>',
        );
        self::assertStringContainsString('8 w', $ops);
    }

    /** A non-uniform scale uses the geometric mean of the two axes. */
    public function testNonUniformScaleUsesTheGeometricMean(): void
    {
        // scale(4, 9) -> sqrt(36) = 6; 12 / 6 = 2.
        $ops = $this->paint(
            '<g transform="scale(4,9)">'
            . '<line x2="10" stroke="#ff0000" stroke-width="12"'
            . ' vector-effect="non-scaling-stroke"/></g>',
        );
        self::assertStringContainsString('2 w', $ops);
    }

    /** The CSS property reaches the painter, not just the attribute. */
    public function testTheCssPropertyFormWorksToo(): void
    {
        $ops = $this->paint(
            '<style>line { vector-effect: non-scaling-stroke; }</style>'
            . '<g transform="scale(10)">'
            . '<line x2="10" stroke="#ff0000" stroke-width="10"/></g>',
        );
        self::assertStringContainsString('1 w', $ops);
    }

    /**
     * A viewBox-imposed scale counts too: it is part of the transform
     * chain between the element and the root, which is the whole point
     * of the effect. The painter is seeded with that chain as its base
     * matrix, exactly as the renderer seeds it for a real document.
     */
    public function testAViewBoxFitScaleIsDividedOutToo(): void
    {
        $doc = (new SvgParser())->parse(
            '<svg xmlns="http://www.w3.org/2000/svg" width="150" height="100"'
            . ' preserveAspectRatio="xMinYMin" viewBox="0 0 10 10">'
            . '<rect x="1" y="2" width="5" height="6" fill="none" stroke="#0000ff"'
            . ' stroke-width="10" vector-effect="non-scaling-stroke"/></svg>',
        );
        (new SvgCascadeProjector())->project($doc);
        $stream = new ContentStream();
        // A 10-unit viewBox fitted into a 150x100 viewport under
        // xMinYMin meet scales by 10, so a 10-unit stroke is one user
        // unit -- ten device pixels, whatever the fit.
        (new Translator())->paint(
            $doc,
            $stream,
            baseMatrix: [10.0, 0.0, 0.0, 10.0, 0.0, 0.0],
        );
        self::assertStringContainsString('1 w', implode("\n", $stream->getOperators()));
    }

    /**
     * SVG 2 §11.6.2 — `markerUnits="strokeWidth"` scales by the USED
     * stroke width, so the effect shrinks the marker with it. Without
     * this the marker was drawn ten times too big in WPT's
     * marker-units-strokewidth-non-scaling-stroke.
     */
    public function testMarkerUnitsUseTheNormalisedStrokeWidth(): void
    {
        $ops = $this->paint(
            '<marker id="m" markerWidth="10" markerHeight="10" refY="5" orient="0">'
            . '<rect width="10" height="10" fill="#00ff00"/></marker>'
            . '<line x2="10" y1="5" y2="5" stroke="#ff0000" stroke-width="10"'
            . ' vector-effect="non-scaling-stroke" marker-start="url(#m)"'
            . ' transform="scale(10)"/>',
        );
        // Marker scale 1 (not 10), placed at the vertex (0,5) less the
        // reference point (0,5).
        self::assertStringContainsString('1 0 0 1 0 0 cm', $ops);
        self::assertStringNotContainsString('10 0 0 10 0 -45 cm', $ops);
    }
}
