<?php

declare(strict_types=1);

namespace Phpdftk\SvgToPdf\Tests;

use Phpdftk\Pdf\Core\Content\ContentStream;
use Phpdftk\Svg\Parser as SvgParser;
use Phpdftk\SvgToPdf\SvgCascadeProjector;
use Phpdftk\SvgToPdf\Translator;
use PHPUnit\Framework\TestCase;

/**
 * SVG 2 §11.6 — `<marker>` placement.
 *
 * `Phpdftk\Svg\Marker` existed in the model with typed accessors and
 * nothing ever read them: the painter's `dispatchElement` skipped
 * `<marker>` at the document level (correctly) and no shape ever
 * pulled one in, so every `marker-start` / `marker-mid` / `marker-end`
 * in the corpus silently painted nothing. Worse, WPT's marker
 * references define their arrowheads with markers too, so test and
 * reference were equally empty and the reftests PASSED — this suite
 * pins the transform algebra those false passes were hiding.
 *
 * The placement transform is, outermost first (§11.6.3):
 * `translate(vertex) rotate(angle) scale(strokeWidth) translate(-ref)`,
 * emitted as a single `cm`.
 */
final class MarkerPaintTest extends TestCase
{
    private function paint(string $body): string
    {
        $doc = (new SvgParser())->parse(
            '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200">'
            . $body . '</svg>',
        );
        // The marker properties INHERIT, so the cascade has to be
        // projected first — `SvgRenderer` does this before painting.
        (new SvgCascadeProjector())->project($doc);
        $stream = new ContentStream();
        (new Translator())->paint($doc, $stream);
        return implode("\n", $stream->getOperators());
    }

    /** A marker with no `orient`, no `viewBox` and unit stroke width. */
    private const string PLAIN_MARKER =
        '<marker id="m" markerWidth="10" markerHeight="10" overflow="visible">'
        . '<rect width="4" height="4" fill="#00ff00"/></marker>';

    public function testMarkerIsPlacedAtEachVertex(): void
    {
        $ops = $this->paint(
            self::PLAIN_MARKER
            . '<path d="M 10,20 L 50,20 L 90,20" stroke-width="1"'
            . ' marker-start="url(#m)" marker-mid="url(#m)" marker-end="url(#m)"/>',
        );
        self::assertStringContainsString('1 0 0 1 10 20 cm', $ops);
        self::assertStringContainsString('1 0 0 1 50 20 cm', $ops);
        self::assertStringContainsString('1 0 0 1 90 20 cm', $ops);
    }

    public function testNoMarkerPropertyEmitsNoMarker(): void
    {
        $ops = $this->paint(self::PLAIN_MARKER . '<path d="M 10,20 L 50,20"/>');
        self::assertStringNotContainsString('1 0 0 1 10 20 cm', $ops);
    }

    /** SVG 2 §11.6.1 — the properties do not apply to `<rect>`. */
    public function testMarkersDoNotApplyToNonPathShapes(): void
    {
        $ops = $this->paint(
            self::PLAIN_MARKER
            . '<rect x="10" y="20" width="5" height="5" marker-start="url(#m)"/>',
        );
        self::assertStringNotContainsString('1 0 0 1 10 20 cm', $ops);
    }

    /** A reference that misses, or names a non-marker, paints nothing. */
    public function testUnresolvableReferencePaintsNothing(): void
    {
        $ops = $this->paint(
            '<rect id="notamarker" width="1" height="1"/>'
            . '<path d="M 10,20 L 50,20" marker-start="url(#notamarker)"'
            . ' marker-mid="url(#missing)"/>',
        );
        self::assertStringNotContainsString('1 0 0 1 10 20 cm', $ops);
    }

    /**
     * `refX` / `refY` line the marker's reference point up with the
     * vertex, so they subtract from the translation.
     */
    public function testReferencePointOffsetsThePlacement(): void
    {
        $ops = $this->paint(
            '<marker id="m" markerWidth="10" markerHeight="10" refX="3" refY="4" overflow="visible">'
            . '<rect width="4" height="4"/></marker>'
            . '<path d="M 10,20 L 50,20" stroke-width="1" marker-start="url(#m)"/>',
        );
        self::assertStringContainsString('1 0 0 1 7 16 cm', $ops);
    }

    /** `markerUnits="strokeWidth"` (the default) scales by the used stroke width. */
    public function testStrokeWidthUnitsScaleTheMarker(): void
    {
        $ops = $this->paint(
            self::PLAIN_MARKER
            . '<path d="M 10,20 L 50,20" stroke-width="3" marker-start="url(#m)"/>',
        );
        self::assertStringContainsString('3 0 0 3 10 20 cm', $ops);
    }

    /** An absent `stroke-width` is 1, not 0 — the marker still paints. */
    public function testAbsentStrokeWidthIsOneNotZero(): void
    {
        $ops = $this->paint(
            self::PLAIN_MARKER
            . '<path d="M 10,20 L 50,20" marker-start="url(#m)"/>',
        );
        self::assertStringContainsString('1 0 0 1 10 20 cm', $ops);
    }

    public function testUserSpaceOnUseUnitsIgnoreTheStrokeWidth(): void
    {
        $ops = $this->paint(
            '<marker id="m" markerWidth="10" markerHeight="10" markerUnits="userSpaceOnUse"'
            . ' overflow="visible"><rect width="4" height="4"/></marker>'
            . '<path d="M 10,20 L 50,20" stroke-width="7" marker-start="url(#m)"/>',
        );
        self::assertStringContainsString('1 0 0 1 10 20 cm', $ops);
    }

    /** A zero-sized marker viewport disables rendering (SVG 2 §11.6.2). */
    public function testZeroSizedMarkerViewportPaintsNothing(): void
    {
        $ops = $this->paint(
            '<marker id="m" markerWidth="0" markerHeight="10"><rect width="4" height="4"/></marker>'
            . '<path d="M 10,20 L 50,20" marker-start="url(#m)"/>',
        );
        self::assertStringNotContainsString('1 0 0 1 10 20 cm', $ops);
    }

    /** A numeric `orient` ignores the path direction entirely. */
    public function testFixedOrientIgnoresThePathDirection(): void
    {
        $ops = $this->paint(
            '<marker id="m" markerWidth="10" markerHeight="10" orient="90" overflow="visible">'
            . '<rect width="4" height="4"/></marker>'
            . '<path d="M 10,20 L 50,20" stroke-width="1" marker-start="url(#m)"/>',
        );
        // rotate(90) in SVG's y-down space: [cos, sin, -sin, cos].
        self::assertMatchesRegularExpression('/0(\.0+)? 1 -1 0(\.0+)? 10 20 cm/', $ops);
    }

    /** `orient="auto"` faces along the path. */
    public function testAutoOrientFacesAlongThePath(): void
    {
        $ops = $this->paint(
            '<marker id="m" markerWidth="10" markerHeight="10" orient="auto" overflow="visible">'
            . '<rect width="4" height="4"/></marker>'
            . '<path d="M 10,20 L 10,60" stroke-width="1" marker-start="url(#m)"/>',
        );
        self::assertMatchesRegularExpression('/0(\.0+)? 1 -1 0(\.0+)? 10 20 cm/', $ops);
    }

    /**
     * `auto-start-reverse` turns ONLY the start marker around, which is
     * what lets one arrowhead definition serve both ends of a line.
     */
    public function testAutoStartReverseTurnsOnlyTheStartMarker(): void
    {
        $ops = $this->paint(
            '<marker id="m" markerWidth="10" markerHeight="10" orient="auto-start-reverse"'
            . ' overflow="visible"><rect width="4" height="4"/></marker>'
            . '<path d="M 10,20 L 90,20" stroke-width="1"'
            . ' marker-start="url(#m)" marker-end="url(#m)"/>',
        );
        self::assertMatchesRegularExpression('/-1 0(\.0+)? -?0(\.0+)? -1 10 20 cm/', $ops);
        self::assertStringContainsString('1 0 0 1 90 20 cm', $ops);
    }

    /**
     * SVG 2 §8.2 puts `overflow: hidden` on `marker` in the UA
     * stylesheet, so the default is to clip to the marker viewport.
     */
    public function testMarkerClipsToItsViewportByDefault(): void
    {
        $ops = $this->paint(
            '<marker id="m" markerWidth="6" markerHeight="4"><rect width="40" height="40"/></marker>'
            . '<path d="M 10,20 L 50,20" marker-start="url(#m)"/>',
        );
        self::assertStringContainsString('0 0 6 4 re', $ops);
        self::assertStringContainsString('W', $ops);
    }

    public function testOverflowVisibleTurnsTheClipOff(): void
    {
        $ops = $this->paint(
            '<marker id="m" markerWidth="6" markerHeight="4" overflow="visible">'
            . '<rect width="40" height="40"/></marker>'
            . '<path d="M 10,20 L 50,20" marker-start="url(#m)"/>',
        );
        self::assertStringNotContainsString('0 0 6 4 re', $ops);
    }

    /**
     * A `viewBox` maps the content into the marker viewport under
     * `preserveAspectRatio`, and `refX` / `refY` are in the viewBox's
     * coordinate system, so they scale with it.
     */
    public function testViewBoxScalesContentAndTheReferencePoint(): void
    {
        $ops = $this->paint(
            '<marker id="m" markerWidth="20" markerHeight="20" viewBox="0 0 10 10"'
            . ' refX="5" refY="5"><rect width="10" height="10"/></marker>'
            . '<path d="M 30,40 L 50,40" stroke-width="1" marker-start="url(#m)"/>',
        );
        // refX 5 in a 10-unit viewBox scaled into a 20-unit viewport is
        // 10 viewport units, so the placement translates to (20, 30).
        self::assertStringContainsString('1 0 0 1 20 30 cm', $ops);
        self::assertStringContainsString('2 0 0 2 0 0 cm', $ops);
    }

    /** Markers on a `<line>` paint even though a line has no fill. */
    public function testLineWithoutStrokeStillPlacesMarkers(): void
    {
        $ops = $this->paint(
            self::PLAIN_MARKER
            . '<line x1="10" y1="20" x2="50" y2="20" marker-start="url(#m)"/>',
        );
        self::assertStringContainsString('1 0 0 1 10 20 cm', $ops);
    }

    public function testPolylineAndPolygonPlaceMarkers(): void
    {
        $ops = $this->paint(
            self::PLAIN_MARKER
            . '<polyline points="10,20 50,20" marker-start="url(#m)"/>'
            . '<polygon points="70,20 90,20 90,40" marker-start="url(#m)"/>',
        );
        self::assertStringContainsString('1 0 0 1 10 20 cm', $ops);
        self::assertStringContainsString('1 0 0 1 70 20 cm', $ops);
    }

    /**
     * SVG 2 §11.6.2 — the `marker` shorthand sets all three longhands,
     * and the longhands inherit, so a declaration on a `<g>` reaches
     * the shapes underneath it.
     */
    public function testMarkerShorthandInheritsFromAnAncestor(): void
    {
        $ops = $this->paint(
            self::PLAIN_MARKER
            . '<g style="marker:url(#m)"><path d="M 10,20 L 50,20"/></g>',
        );
        self::assertStringContainsString('1 0 0 1 10 20 cm', $ops);
        self::assertStringContainsString('1 0 0 1 50 20 cm', $ops);
    }

    /** A longhand on the element beats the shorthand inherited above it. */
    public function testLonghandOnTheElementBeatsAnInheritedShorthand(): void
    {
        $ops = $this->paint(
            self::PLAIN_MARKER
            . '<marker id="other" markerWidth="10" markerHeight="10" refX="1" overflow="visible">'
            . '<rect width="4" height="4"/></marker>'
            . '<g style="marker:url(#m)">'
            . '<path d="M 10,20 L 50,20" style="marker-start:url(#other)"/></g>',
        );
        // The start vertex uses #other (refX 1 shifts it to x=9); the
        // end vertex still uses the inherited #m.
        self::assertStringContainsString('1 0 0 1 9 20 cm', $ops);
        self::assertStringContainsString('1 0 0 1 50 20 cm', $ops);
    }
}
