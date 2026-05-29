<?php

declare(strict_types=1);

namespace Phpdftk\SvgToPdf\Gradient;

use Phpdftk\Color\RgbColor;
use Phpdftk\Geometry\Point;
use Phpdftk\Pdf\Core\Content\ContentStream;
use Phpdftk\Pdf\Core\Graphics\Pattern\ShadingPattern;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Writer\Page;
use Phpdftk\Pdf\Writer\PdfDoc;
use Phpdftk\Pdf\Writer\PdfWriter;
use Phpdftk\Svg\Element;
use Phpdftk\Svg\Gradient\Gradient;
use Phpdftk\Svg\Gradient\LinearGradient;
use Phpdftk\Svg\Gradient\RadialGradient;
use Phpdftk\Svg\Gradient\Stop;
use Phpdftk\Svg\SvgDocument;
use Phpdftk\Svg\Value\Transform;
use Phpdftk\SvgToPdf\Geometry\BoundingBox;

/**
 * Register an SVG `<linearGradient>` / `<radialGradient>` as a PDF
 * `ShadingPattern` and configure the supplied content stream to fill or
 * stroke with it.
 *
 * Requires a `PdfWriter` (to register the shading objects) and a `Page`
 * (to attach the pattern resource). The Translator only calls into the
 * painter when both are available — without them the gradient silently
 * falls back to no paint, matching SVG 2's "invalid → no paint" rule.
 *
 * Scope at 3O:
 *
 *  - Linear (Type 2) and radial (Type 3) shadings.
 *  - N-stop interpolation via `PdfDoc::addLinearGradientStops` /
 *    `addRadialGradientStops` (Type 3 stitching of Type 2 segments
 *    when N > 2).
 *  - `gradientUnits = userSpaceOnUse | objectBoundingBox` with the
 *    element's axis-aligned bbox supplied by
 *    `Phpdftk\SvgToPdf\Geometry\BoundingBox`.
 *
 * Deferred (documented in plan and README):
 *
 *  - `spreadMethod: reflect | repeat` (treated as `pad`).
 *  - `gradientTransform` (would need to bake into the shading's
 *    `Matrix`, deferred).
 *  - `radialGradient`'s `fx` / `fy` / `fr` focal-point and focal-radius
 *    (we use cx/cy and r; PDF's two-circle radial supports it but the
 *    SVG-to-PDF mapping needs more care than 3O has room for).
 *  - Stops in a colour space other than sRGB. The painter coerces to
 *    `RgbColor` via the existing `phpdftk/color` converters.
 */
final class GradientPainter
{
    public function __construct(
        private readonly PdfWriter $writer,
        private readonly Page $page,
        private readonly SvgDocument $document,
    ) {}

    /**
     * Configure `$stream` for a gradient fill keyed by the given id.
     * Returns true when the gradient was successfully registered and
     * the stream is now set up for `f`/`B`; false when the gradient is
     * missing, empty, or otherwise unrenderable (caller must skip the
     * fill).
     */
    public function applyAsFill(string $gradientId, Element $element, ContentStream $stream): bool
    {
        $pattern = $this->registerForElement($gradientId, $element);
        if ($pattern === null) {
            return false;
        }
        $name = $this->page->useGradient($pattern);
        $stream->setFillColorSpace('Pattern');
        $stream->setFillColor('/' . $name);
        return true;
    }

    /** Same shape as `applyAsFill` but for the stroke channel. */
    public function applyAsStroke(string $gradientId, Element $element, ContentStream $stream): bool
    {
        $pattern = $this->registerForElement($gradientId, $element);
        if ($pattern === null) {
            return false;
        }
        $name = $this->page->useGradient($pattern);
        $stream->setStrokeColorSpace('Pattern');
        $stream->setStrokeColor('/' . $name);
        return true;
    }

    private function registerForElement(string $gradientId, Element $element): ?ShadingPattern
    {
        $gradient = $this->document->findById($gradientId);
        if (!$gradient instanceof Gradient) {
            return null;
        }
        $stops = $this->resolveStops($gradient);
        if (count($stops) < 2) {
            return null;
        }
        $bbox = null;
        if ($gradient->gradientUnits() === 'objectBoundingBox') {
            $bbox = BoundingBox::compute($element);
            if ($bbox === null) {
                return null;
            }
        }
        $doc = PdfDoc::wrap($this->writer);
        $pattern = match (true) {
            $gradient instanceof LinearGradient => $this->registerLinear($gradient, $stops, $bbox, $doc),
            $gradient instanceof RadialGradient => $this->registerRadial($gradient, $stops, $bbox, $doc),
            default => null,
        };
        if ($pattern !== null) {
            self::applyGradientTransform($pattern, $gradient->gradientTransform());
        }
        return $pattern;
    }

    /**
     * Bake the SVG `gradientTransform` attribute into the PDF
     * `ShadingPattern`'s `Matrix` entry. PDF pattern Matrix maps
     * pattern space (where `Coords` live) to user space, which is
     * exactly the SVG-2 §13.6.5 semantic for `gradientTransform`.
     */
    private static function applyGradientTransform(ShadingPattern $pattern, ?Transform $transform): void
    {
        if ($transform === null) {
            return;
        }
        $matrix = $transform->toMatrix();
        $pattern->matrix = new PdfArray([
            new PdfNumber($matrix[0]),
            new PdfNumber($matrix[1]),
            new PdfNumber($matrix[2]),
            new PdfNumber($matrix[3]),
            new PdfNumber($matrix[4]),
            new PdfNumber($matrix[5]),
        ]);
    }

    /**
     * @param list<array{offset: float, rgb: array{float, float, float}}> $stops
     * @param array{minX: float, minY: float, width: float, height: float}|null $bbox
     */
    private function registerLinear(
        LinearGradient $gradient,
        array $stops,
        ?array $bbox,
        PdfDoc $doc,
    ): ShadingPattern {
        // SVG 2 §13.6.5 — defaults differ per units mode:
        //
        //   objectBoundingBox: x1=0, y1=0, x2=1, y2=0
        //   userSpaceOnUse:    x1=0%, y1=0%, x2=100%, y2=0%
        //
        // Both default to a left-to-right horizontal gradient; the
        // difference is the resolution coordinate.
        $x1 = $gradient->x1() ?? 0.0;
        $y1 = $gradient->y1() ?? 0.0;
        $x2 = $gradient->x2() ?? ($bbox !== null ? 1.0 : 0.0);
        $y2 = $gradient->y2() ?? 0.0;
        if ($bbox !== null) {
            $x1 = $bbox['minX'] + $x1 * $bbox['width'];
            $y1 = $bbox['minY'] + $y1 * $bbox['height'];
            $x2 = $bbox['minX'] + $x2 * $bbox['width'];
            $y2 = $bbox['minY'] + $y2 * $bbox['height'];
        }
        return $doc->addLinearGradientStops(new Point($x1, $y1), new Point($x2, $y2), $stops);
    }

    /**
     * @param list<array{offset: float, rgb: array{float, float, float}}> $stops
     * @param array{minX: float, minY: float, width: float, height: float}|null $bbox
     */
    private function registerRadial(
        RadialGradient $gradient,
        array $stops,
        ?array $bbox,
        PdfDoc $doc,
    ): ShadingPattern {
        // SVG 2 §13.7.5 defaults — cx, cy, r default to 50% / 50% / 50%
        // in the resolution mode's coordinate space. `fx` / `fy`
        // default to the centre point; `fr` defaults to 0.
        $cx = $gradient->cx() ?? ($bbox !== null ? 0.5 : 0.0);
        $cy = $gradient->cy() ?? ($bbox !== null ? 0.5 : 0.0);
        $r = $gradient->r() ?? ($bbox !== null ? 0.5 : 0.0);
        $fx = $gradient->fx() ?? $cx;
        $fy = $gradient->fy() ?? $cy;
        $fr = $gradient->fr() ?? 0.0;
        if ($r <= 0.0) {
            // Zero-radius radial paints nothing; return a shading that
            // produces transparent output. Simpler: bail and let
            // `applyAsFill` fall back to no fill.
            $r = 0.0001;
        }
        if ($bbox !== null) {
            $cx = $bbox['minX'] + $cx * $bbox['width'];
            $cy = $bbox['minY'] + $cy * $bbox['height'];
            $fx = $bbox['minX'] + $fx * $bbox['width'];
            $fy = $bbox['minY'] + $fy * $bbox['height'];
            // SVG specifies `r` against the bbox diagonal scaled by
            // `√2/2` (the "user space units" mapping). At 3O we used
            // the larger axis as a conservative approximation — the
            // gradient still emanates from the centre, scaled to
            // cover the bbox. `fr` rides on the same factor.
            $axis = max($bbox['width'], $bbox['height']);
            $r *= $axis;
            $fr *= $axis;
        }
        return $doc->addRadialGradientStops(
            new Point($fx, $fy),
            max(0.0, $fr),
            new Point($cx, $cy),
            $r,
            $stops,
        );
    }

    /**
     * Resolve the gradient's stop list down to the `{offset, rgb}` tuple
     * format `PdfDoc::addLinearGradientStops` expects. Walks `Gradient::
     * stops` (which already does the cycle-safe href chain), drops
     * stops with unparseable colours, and clamps the offset sequence
     * to monotonically non-decreasing per CSS Images §3.5.1.
     *
     * @return list<array{offset: float, rgb: array{float, float, float}}>
     */
    private function resolveStops(Gradient $gradient): array
    {
        $stops = $gradient->stops($this->document);
        if ($stops === []) {
            return [];
        }
        $out = [];
        $lastOffset = 0.0;
        foreach ($stops as $stop) {
            $rgb = $this->stopColor($stop);
            if ($rgb === null) {
                continue;
            }
            $offset = max($lastOffset, $stop->offset());
            $lastOffset = $offset;
            $out[] = ['offset' => $offset, 'rgb' => $rgb];
        }
        if ($out === []) {
            return [];
        }
        // Ensure the function domain covers [0, 1] — pad the ends with
        // the first/last stop value if the author didn't anchor them.
        if ($out[0]['offset'] > 0.0) {
            array_unshift($out, ['offset' => 0.0, 'rgb' => $out[0]['rgb']]);
        }
        if ($out[count($out) - 1]['offset'] < 1.0) {
            $out[] = ['offset' => 1.0, 'rgb' => $out[count($out) - 1]['rgb']];
        }
        return $out;
    }

    /**
     * Coerce a `<stop>`'s `stop-color` down to a 3-tuple sRGB triple in
     * `[0, 1]`. Stops without a usable colour are dropped by the caller
     * so the function builder never sees an unparseable input.
     *
     * @return array{float, float, float}|null
     */
    private function stopColor(Stop $stop): ?array
    {
        $color = $stop->stopColor();
        if ($color === null) {
            return null;
        }
        $rgb = $color instanceof RgbColor ? $color : null;
        if ($rgb === null && method_exists($color, 'toRgb')) {
            $candidate = $color->toRgb();
            $rgb = $candidate instanceof RgbColor ? $candidate : null;
        }
        if ($rgb === null) {
            $components = $color->toArray();
            if (count($components) !== 3) {
                return null;
            }
            return [(float) $components[0], (float) $components[1], (float) $components[2]];
        }
        return [$rgb->r, $rgb->g, $rgb->b];
    }
}
