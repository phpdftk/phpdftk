<?php

declare(strict_types=1);

namespace Phpdftk\SvgToPdf;

use Phpdftk\Color\CmykColor;
use Phpdftk\Color\ColorInterface;
use Phpdftk\Color\GrayColor;
use Phpdftk\Color\RgbColor;
use Phpdftk\ImageMetadata\ImageParser;
use Phpdftk\Pdf\Core\Content\ContentStream;
use Phpdftk\Pdf\Core\Document\GroupAttributes;
use Phpdftk\Pdf\Core\Graphics\ExtGState;
use Phpdftk\Pdf\Core\Graphics\SoftMask;
use Phpdftk\Pdf\Core\Graphics\XObject\FormXObject;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Writer\Page;
use Phpdftk\Pdf\Writer\PdfWriter;
use Phpdftk\SvgToPdf\Gradient\GradientPainter;
use Phpdftk\SvgToPdf\Text\FontResolver;
use Phpdftk\Svg\ClipPath;
use Phpdftk\Svg\Defs;
use Phpdftk\Svg\Element;
use Phpdftk\Svg\Image as SvgImage;
use Phpdftk\Svg\Mask;
use Phpdftk\Svg\Symbol;
use Phpdftk\Svg\Use_;
use Phpdftk\SvgToPdf\Geometry\BoundingBox;
use Phpdftk\Svg\Path;
use Phpdftk\Svg\Path\ArcTo;
use Phpdftk\Svg\Path\ClosePath;
use Phpdftk\Svg\Path\CurveTo;
use Phpdftk\Svg\Path\HorizontalLineTo;
use Phpdftk\Svg\Path\LineTo;
use Phpdftk\Svg\Path\MoveTo;
use Phpdftk\Svg\Path\PathCommand;
use Phpdftk\Svg\Path\QuadraticCurveTo;
use Phpdftk\Svg\Path\SmoothCurveTo;
use Phpdftk\Svg\Path\SmoothQuadraticCurveTo;
use Phpdftk\Svg\Path\VerticalLineTo;
use Phpdftk\Svg\Shape\Circle;
use Phpdftk\Svg\Shape\Ellipse;
use Phpdftk\Svg\Shape\Line;
use Phpdftk\Svg\Shape\Polygon;
use Phpdftk\Svg\Shape\Polyline;
use Phpdftk\Svg\Shape\Rect;
use Phpdftk\Svg\SvgDocument;
use Phpdftk\Svg\Text as TextNode;
use Phpdftk\Svg\Text\TextElement;
use Phpdftk\Svg\Value\Paint;
use Phpdftk\Svg\Value\Paint\CurrentColor;
use Phpdftk\Svg\Value\Paint\None_;
use Phpdftk\Svg\Value\Paint\SolidColor;
use Phpdftk\Svg\Value\Paint\Url;
use Phpdftk\SvgToPdf\Path\ArcToCubic;
use Phpdftk\SvgToPdf\Path\PathPainterState;

/**
 * Translates a parsed `Phpdftk\Svg\SvgDocument` into PDF content-stream
 * operators. The translator is a thin recursive walk: each element is
 * dispatched to a per-shape painter that emits the right path and
 * `f`/`S`/`B` operator combination.
 *
 * Coordinate convention: SVG and PDF disagree on Y-axis direction (SVG
 * Y-down, PDF Y-up). The translator emits SVG coordinates verbatim — the
 * caller is responsible for setting up a PDF transformation (`cm`) that
 * flips and translates if it wants the SVG to appear at a specific PDF
 * position. Tests can paint directly into a fresh PDF stream because the
 * default user space happens to put numbers in a viewable range for small
 * SVGs.
 *
 * What 3K covers: basic shapes (`<rect>`, `<circle>`, `<ellipse>`,
 * `<line>`, `<polyline>`, `<polygon>`) and the SolidColor fill / stroke
 * paint cases. `<path>` lands in 3L, `<g>` + transforms in 3M, gradients
 * in 3O, text in 3P, use/clip/mask/image in 3Q. Until then unrecognised
 * elements are walked through transparently — their children paint as if
 * the unknown container weren't there.
 *
 * Default paint per SVG 2 §13.2.1: black fill, no stroke. The translator
 * applies that fallback when no explicit fill is set on the element.
 */
final class Translator
{
    /**
     * Cubic-Bézier "magic number" approximating a unit-circle quarter
     * arc — `(4/3) · tan(π/8) ≈ 0.5522847498`. Standard κ for
     * `<circle>` / `<ellipse>` rendering.
     */
    private const float KAPPA = 0.5522847498;

    /**
     * Paint a parsed SVG document into the given content stream.
     *
     * When `$page` is supplied, the painter registers an `ExtGState`
     * resource on that page for any element that carries `opacity`,
     * `fill-opacity`, or `stroke-opacity` < 1 and emits the `gs`
     * operator to invoke it. Without a `$page` reference opacity
     * attributes are silently ignored — the painter falls back to
     * fully-opaque rendering.
     *
     * When `$page` AND `$writer` are both supplied, gradient paint
     * references (`fill="url(#id)"`) resolve through the writer's
     * `PdfDoc` for shading registration and the page's
     * `useGradient` for resource attachment. Without them, gradient
     * fills fall back to no paint per SVG 2's "invalid → no paint"
     * semantics.
     */
    public function paint(
        SvgDocument $document,
        ContentStream $stream,
        ?Page $page = null,
        ?PdfWriter $writer = null,
        bool $compensateTextFlip = false,
    ): void {
        $this->page = $page;
        $this->writer = $writer;
        $this->document = $document;
        $this->compensateTextFlip = $compensateTextFlip;
        $this->gradientPainter = $page !== null && $writer !== null
            ? new GradientPainter($writer, $page, $document)
            : null;
        $this->fontResolver = $page !== null && $writer !== null
            ? new FontResolver($writer, $page)
            : null;
        try {
            $viewBox = $document->viewBox();
            if ($viewBox !== null && ($viewBox[0] !== 0.0 || $viewBox[1] !== 0.0)) {
                // SVG 2 §7 — the viewBox's `min-x`/`min-y` shift the
                // origin of the local coordinate system. The proper
                // viewBox-to-viewport mapping (with `preserveAspectRatio`)
                // needs a caller-supplied target rectangle, so it lives
                // in the 3R adapter layer; here we honour just the
                // translation so the painted content stays anchored
                // correctly relative to the viewBox.
                $stream->saveGraphicsState();
                $stream->concatMatrix(1.0, 0.0, 0.0, 1.0, -$viewBox[0], -$viewBox[1]);
                $this->paintChildren($document, $stream);
                $stream->restoreGraphicsState();
                return;
            }
            $this->paintChildren($document, $stream);
        } finally {
            $this->page = null;
            $this->writer = null;
            $this->document = null;
            $this->gradientPainter = null;
            $this->fontResolver = null;
            $this->compensateTextFlip = false;
        }
    }

    private ?Page $page = null;
    private ?PdfWriter $writer = null;
    private ?SvgDocument $document = null;
    private ?GradientPainter $gradientPainter = null;
    private ?FontResolver $fontResolver = null;
    private bool $compensateTextFlip = false;

    private function paintChildren(Element $parent, ContentStream $stream): void
    {
        foreach ($parent->children as $child) {
            if ($child instanceof Element) {
                $this->paintElement($child, $stream);
            }
        }
    }

    private function paintElement(Element $element, ContentStream $stream): void
    {
        // Any of these scope-leaking attributes triggers a `q`/`Q` wrap
        // so the state doesn't leak across siblings:
        //
        //   - transform: emits `cm`.
        //   - opacity / fill-opacity / stroke-opacity (< 1): emits `gs`.
        //   - stroke params (w / J / j / M / d): each emits its own op.
        //   - clip-path: emits the clip region inside the same wrap.
        //   - mask: emits `gs` referencing a SMask-bearing ExtGState.
        //
        // Painting the same shape with all defaults stays a one-shot
        // op stream — no overhead when none of the above is set.
        $transform = $element->transform();
        $opacityGs = $this->resolveOpacityState($element);
        $needsStrokeParams = $this->needsStrokeParams($element);
        $clipPath = $this->resolveClipPath($element);
        $maskGs = $this->resolveMaskState($element);
        $needsWrap = $transform !== null
            || $opacityGs !== null
            || $needsStrokeParams
            || $clipPath !== null
            || $maskGs !== null;

        if (!$needsWrap) {
            $this->dispatchElement($element, $stream);
            return;
        }

        $stream->saveGraphicsState();
        if ($transform !== null) {
            $matrix = $transform->toMatrix();
            $stream->concatMatrix(
                $matrix[0],
                $matrix[1],
                $matrix[2],
                $matrix[3],
                $matrix[4],
                $matrix[5],
            );
        }
        if ($opacityGs !== null) {
            $stream->setGraphicsState($opacityGs);
        }
        if ($needsStrokeParams) {
            $this->applyStrokeParams($element, $stream);
        }
        if ($clipPath !== null) {
            $this->applyClipPath($clipPath, $element, $stream);
        }
        if ($maskGs !== null) {
            $stream->setGraphicsState($maskGs);
        }
        $this->dispatchElement($element, $stream);
        $stream->restoreGraphicsState();
    }

    /**
     * Resolve `mask="url(#id)"` to a registered `ExtGState` whose
     * `/SMask` references a Form XObject containing the mask's
     * painted children. Returns the resource name to invoke via
     * `gs`, or null when no mask is set / can't be resolved.
     *
     * Pipeline:
     *
     *   1. Paint the `<mask>`'s children into a new `ContentStream`
     *      (running the full Translator pipeline so gradients, fonts,
     *      images, etc. all register on the host page).
     *   2. Wrap the resulting bytes in a Form XObject. `/Group /S
     *      Transparency /CS DeviceGray` so the form's pixels become an
     *      alpha channel via their luminance.
     *   3. Build a `SoftMask` dict with `/S Luminosity` and `/BC [0]`
     *      so the backdrop outside the mask region is black (hidden).
     *   4. Drop the SMask in an `ExtGState`, register, and attach to
     *      the page's resources under a stable name.
     *
     * Scope at 3R+8:
     *
     *   - `maskContentUnits = objectBoundingBox` applies a bbox `cm`
     *     to the mask content stream so authored coords are in [0, 1].
     *   - Default mask region is the masked element's bbox. Proper
     *     SVG 2 §14.5.4 defaults (`-10%` / `120%` extension) and the
     *     explicit `x` / `y` / `width` / `height` attributes are
     *     deferred to a follow-up.
     */
    private function resolveMaskState(Element $element): ?string
    {
        if ($this->writer === null
            || $this->page === null
            || $this->document === null
        ) {
            return null;
        }
        $raw = $element->getAttribute('mask');
        if ($raw === null) {
            return null;
        }
        $trimmed = trim($raw);
        if ($trimmed === 'none') {
            return null;
        }
        if (preg_match('/^url\(\s*#([^)\s]+)\s*\)/i', $trimmed, $m) !== 1) {
            return null;
        }
        $referent = $this->document->findById($m[1]);
        if (!$referent instanceof Mask) {
            return null;
        }
        $elementBbox = BoundingBox::compute($element);
        if ($elementBbox === null) {
            return null;
        }
        $region = self::computeMaskRegion($referent, $elementBbox);

        $maskStream = new ContentStream();
        if ($referent->maskContentUnits() === 'objectBoundingBox') {
            // Reify mask children's [0, 1] coords against the masked
            // element's bbox (the same reference frame the masked
            // element's geometry inhabits — not the mask region).
            $maskStream->concatMatrix(
                $elementBbox['width'],
                0.0,
                0.0,
                $elementBbox['height'],
                $elementBbox['minX'],
                $elementBbox['minY'],
            );
        }
        foreach ($referent->children as $child) {
            if ($child instanceof Element) {
                $this->paintElement($child, $maskStream);
            }
        }
        $operatorBytes = implode("\n", $maskStream->getOperators());
        $form = new FormXObject(
            new PdfArray([
                new PdfNumber($region['minX']),
                new PdfNumber($region['minY']),
                new PdfNumber($region['minX'] + $region['width']),
                new PdfNumber($region['minY'] + $region['height']),
            ]),
            $operatorBytes,
        );
        // Transparency group with the DeviceGray colour space so the
        // luminance of the painted pixels becomes the mask's alpha.
        $group = new GroupAttributes('Transparency');
        $group->cs = new PdfName('DeviceGray');
        $form->group = $group;
        $this->writer->register($form);

        // SVG 2 §14.5: `mask-type="alpha"` uses the painted pixels'
        // alpha channel directly; the default `luminance` (also
        // covering the old `mask-type` absent case) uses the
        // luminance of the RGB pixels. PDF maps these to the SoftMask
        // `/S /Alpha` and `/S /Luminosity` modes.
        $maskSubtype = strtolower(trim($referent->getAttribute('mask-type') ?? ''));
        $smask = new SoftMask(
            $maskSubtype === 'alpha' ? 'Alpha' : 'Luminosity',
            new PdfReference($form->objectNumber),
        );
        // Backdrop colour black ([0]) so anywhere the mask content
        // doesn't paint stays hidden — matches SVG 2's "outside the
        // mask region the alpha is 0" semantic.
        $smask->bc = new PdfArray([new PdfNumber(0)]);

        $gstate = new ExtGState();
        $gstate->sMask = $smask;
        $this->writer->register($gstate);

        $name = 'GS_mask_' . $gstate->objectNumber;
        $resources = $this->page->corePage()->resources;
        if ($resources !== null) {
            $resources->extGState[$name] = new PdfReference($gstate->objectNumber);
        }
        return $name;
    }

    /**
     * Compute the rectangular region that the mask covers, in user
     * space. SVG 2 §14.5.4 defaults:
     *
     *  - `maskUnits="objectBoundingBox"` (default): `x=-10%, y=-10%,
     *    width=120%, height=120%` of the masked element's bounding
     *    box. The 10% pad on each side is what makes the mask
     *    naturally reach a hair beyond the painted geometry so
     *    anti-aliased edges aren't clipped.
     *  - `maskUnits="userSpaceOnUse"`: SVG defaults to the viewport
     *    rect (-10% etc. of the viewport), which we don't have direct
     *    access to here — fall back to the masked element's bbox
     *    (matches 3R+8 behaviour for unset attributes).
     *
     * @param array{minX: float, minY: float, width: float, height: float} $elementBbox
     * @return array{minX: float, minY: float, width: float, height: float}
     */
    private static function computeMaskRegion(Mask $mask, array $elementBbox): array
    {
        $bboxMode = $mask->maskUnits() === 'objectBoundingBox';

        if ($bboxMode) {
            $x = $mask->x() ?? -0.1;
            $y = $mask->y() ?? -0.1;
            $w = $mask->width() ?? 1.2;
            $h = $mask->height() ?? 1.2;
            return [
                'minX' => $elementBbox['minX'] + $x * $elementBbox['width'],
                'minY' => $elementBbox['minY'] + $y * $elementBbox['height'],
                'width' => $w * $elementBbox['width'],
                'height' => $h * $elementBbox['height'],
            ];
        }

        return [
            'minX' => $mask->x() ?? $elementBbox['minX'],
            'minY' => $mask->y() ?? $elementBbox['minY'],
            'width' => $mask->width() ?? $elementBbox['width'],
            'height' => $mask->height() ?? $elementBbox['height'],
        ];
    }

    /**
     * Resolve an element's `clip-path="url(#id)"` reference to the
     * matching `<clipPath>` element. Returns null when no clip-path is
     * set, the reference can't be parsed, or the id doesn't resolve to
     * a clipPath — each case falls through to the unclipped paint per
     * SVG 2's "invalid → no clip" rule.
     */
    private function resolveClipPath(Element $element): ?ClipPath
    {
        if ($this->document === null) {
            return null;
        }
        $raw = $element->getAttribute('clip-path');
        if ($raw === null) {
            return null;
        }
        $trimmed = trim($raw);
        if ($trimmed === 'none') {
            return null;
        }
        if (preg_match('/^url\(\s*#([^)\s]+)\s*\)/i', $trimmed, $m) !== 1) {
            return null;
        }
        $referent = $this->document->findById($m[1]);
        return $referent instanceof ClipPath ? $referent : null;
    }

    /**
     * Construct the PDF clipping region from the `<clipPath>` element's
     * geometry and emit `W`/`W*` + `n` so subsequent painting is
     * scoped to it. `clipPathUnits="objectBoundingBox"` is honoured by
     * sandwiching the path construction between a bbox-space `cm` and
     * its inverse — the clip region is "frozen" by `W` in user space,
     * so applying the inverse `cm` returns the CTM to its pre-clip
     * state without disturbing the established region.
     */
    private function applyClipPath(ClipPath $clipPath, Element $element, ContentStream $stream): void
    {
        $useBbox = $clipPath->clipPathUnits() === 'objectBoundingBox';
        $bbox = $useBbox ? BoundingBox::compute($element) : null;
        if ($useBbox && $bbox === null) {
            // bbox required for objectBoundingBox mode but unavailable
            // (e.g. `<path>` element with no bbox helper at 3R+3) —
            // fall back to no clip rather than emitting a broken clip.
            return;
        }

        $bboxMatrix = $bbox === null
            ? null
            : [$bbox['width'], 0.0, 0.0, $bbox['height'], $bbox['minX'], $bbox['minY']];
        $clipTransform = $clipPath->transform()?->toMatrix();

        // Apply outer→inner: bbox first, then the clipPath's own
        // `transform`. Children paint in their authored coordinate
        // system; the cm composition reifies that into user space
        // for the W operator to capture.
        if ($bboxMatrix !== null) {
            $stream->concatMatrix(...$bboxMatrix);
        }
        if ($clipTransform !== null) {
            $stream->concatMatrix(...$clipTransform);
        }
        foreach ($clipPath->children as $child) {
            if ($child instanceof Element) {
                $this->emitElementPath($child, $stream);
            }
        }
        // Undo the cms in reverse order so the CTM is back to the
        // pre-clip user space. The clip region established by `W`
        // already lives in device space, so it survives the CTM
        // changes.
        if ($clipTransform !== null) {
            $stream->concatMatrix(...self::inverseAffine($clipTransform));
        }
        if ($bboxMatrix !== null) {
            $stream->concatMatrix(...self::inverseAffine($bboxMatrix));
        }

        $rule = self::resolveClipRule($clipPath);
        if ($rule === 'evenodd') {
            $stream->clipEvenOdd();
        } else {
            $stream->clip();
        }
        $stream->endPath();
    }

    /**
     * Inverse of a 3×2 affine matrix in PDF `[a b c d e f]` order. The
     * full 3×3 affine has bottom row `[0 0 1]` so we only need the six
     * SVG/PDF entries. Singular matrices (det == 0) shouldn't occur
     * for any transform we generate; if one does, returning identity
     * makes the worst-case behaviour "no inverse applied" rather than
     * a divide-by-zero.
     *
     * @param array{float, float, float, float, float, float} $m
     * @return array{float, float, float, float, float, float}
     */
    private static function inverseAffine(array $m): array
    {
        [$a, $b, $c, $d, $e, $f] = $m;
        $det = $a * $d - $b * $c;
        if (abs($det) < 1.0e-12) {
            return [1.0, 0.0, 0.0, 1.0, 0.0, 0.0];
        }
        return [
            $d / $det,
            -$b / $det,
            -$c / $det,
            $a / $det,
            ($c * $f - $d * $e) / $det,
            ($b * $e - $a * $f) / $det,
        ];
    }

    /**
     * `clip-rule` per SVG 2 §14.4.4. The attribute lives on the
     * `<clipPath>` element itself at 3R+3; per-child clip-rule support
     * (the spec allows overriding on individual children) lands later.
     *
     * @return 'nonzero'|'evenodd'
     */
    private static function resolveClipRule(ClipPath $clipPath): string
    {
        $raw = $clipPath->getAttribute('clip-rule');
        return strtolower(trim($raw ?? '')) === 'evenodd' ? 'evenodd' : 'nonzero';
    }

    /**
     * Emit just the geometry of an element — the path operators that
     * would have ended up before the fill/stroke op. Used by
     * `applyClipPath` to assemble a clipping region without painting.
     */
    private function emitElementPath(Element $element, ContentStream $stream): void
    {
        match (true) {
            $element instanceof Rect => $this->emitRectPath($element, $stream),
            $element instanceof Circle => $this->emitCirclePath($element, $stream),
            $element instanceof Ellipse => $this->emitEllipsePathFor($element, $stream),
            $element instanceof Polyline => $this->emitPolylinePath($element, $stream),
            $element instanceof Polygon => $this->emitPolygonPath($element, $stream),
            $element instanceof Path => $this->emitPathPath($element, $stream),
            // `<line>` and other non-area-enclosing children don't
            // contribute to a clip region per SVG 2 §14.4.1.
            default => null,
        };
    }

    private function emitRectPath(Rect $rect, ContentStream $stream): void
    {
        $w = $rect->width();
        $h = $rect->height();
        if ($w > 0.0 && $h > 0.0) {
            $stream->rectangle($rect->x(), $rect->y(), $w, $h);
        }
    }

    private function emitCirclePath(Circle $circle, ContentStream $stream): void
    {
        if ($circle->r() > 0.0) {
            $this->emitEllipsePath($stream, $circle->cx(), $circle->cy(), $circle->r(), $circle->r());
        }
    }

    private function emitEllipsePathFor(Ellipse $ellipse, ContentStream $stream): void
    {
        $rx = $ellipse->rx();
        $ry = $ellipse->ry();
        if ($rx !== null && $ry !== null && $rx > 0.0 && $ry > 0.0) {
            $this->emitEllipsePath($stream, $ellipse->cx(), $ellipse->cy(), $rx, $ry);
        }
    }

    private function emitPolylinePath(Polyline $polyline, ContentStream $stream): void
    {
        $points = $polyline->points();
        if (count($points) >= 2) {
            $this->emitPolyPath($stream, $points, closed: false);
        }
    }

    private function emitPolygonPath(Polygon $polygon, ContentStream $stream): void
    {
        $points = $polygon->points();
        if (count($points) >= 3) {
            $this->emitPolyPath($stream, $points, closed: true);
        }
    }

    private function emitPathPath(Path $path, ContentStream $stream): void
    {
        $commands = $path->d()->commands;
        if ($commands === []) {
            return;
        }
        $state = new PathPainterState();
        foreach ($commands as $command) {
            $this->emitPathCommand($command, $stream, $state);
        }
    }

    /**
     * Register (or reuse) the `ExtGState` resource encoding this
     * element's effective opacity. Returns the resource name or null
     * when no `gs` op is needed (no Page reference, or every opacity
     * channel is already ≥ 0.999).
     */
    private function resolveOpacityState(Element $element): ?string
    {
        if ($this->page === null) {
            return null;
        }
        $opacity = $element->opacity() ?? 1.0;
        $fillOpacity = ($element->fillOpacity() ?? 1.0) * $opacity;
        $strokeOpacity = ($element->strokeOpacity() ?? 1.0) * $opacity;
        if ($fillOpacity >= 0.999 && $strokeOpacity >= 0.999) {
            return null;
        }
        return $this->page->ensureOpacityState($strokeOpacity, $fillOpacity);
    }

    /**
     * Whether the element carries any stroke parameter that would
     * leak past a sibling shape if emitted inline. Used to decide
     * whether to wrap the element's painting in `q`/`Q`.
     */
    private function needsStrokeParams(Element $element): bool
    {
        if ($element->stroke() === null || $element->stroke() instanceof None_) {
            return false;
        }
        if ($element->strokeWidth() !== null) {
            return true;
        }
        if ($element->strokeLinecap() !== null) {
            return true;
        }
        if ($element->strokeLinejoin() !== null) {
            return true;
        }
        if ($element->strokeMiterlimit() !== null) {
            return true;
        }
        if ($element->strokeDasharray() !== []) {
            return true;
        }
        if ($element->strokeDashoffset() !== null) {
            return true;
        }
        return false;
    }

    private function applyStrokeParams(Element $element, ContentStream $stream): void
    {
        $width = $element->strokeWidth();
        if ($width !== null) {
            $stream->setLineWidth($width);
        }
        $cap = $element->strokeLinecap();
        if ($cap !== null) {
            $stream->setLineCap(match ($cap) {
                'round' => 1,
                'square' => 2,
                default => 0, // butt
            });
        }
        $join = $element->strokeLinejoin();
        if ($join !== null) {
            $stream->setLineJoin(match ($join) {
                'round' => 1,
                'bevel' => 2,
                default => 0, // miter / miter-clip / arcs all fall back to PDF's miter
            });
        }
        $miterLimit = $element->strokeMiterlimit();
        if ($miterLimit !== null) {
            $stream->setMiterLimit($miterLimit);
        }
        $dash = $element->strokeDasharray();
        if ($dash !== []) {
            $offset = (int) round($element->strokeDashoffset() ?? 0.0);
            $stream->setDashPattern($dash, $offset);
        }
    }

    private function dispatchElement(Element $element, ContentStream $stream): void
    {
        match (true) {
            $element instanceof Rect => $this->paintRect($element, $stream),
            $element instanceof Circle => $this->paintCircle($element, $stream),
            $element instanceof Ellipse => $this->paintEllipse($element, $stream),
            $element instanceof Line => $this->paintLine($element, $stream),
            $element instanceof Polyline => $this->paintPolyline($element, $stream),
            $element instanceof Polygon => $this->paintPolygon($element, $stream),
            $element instanceof Path => $this->paintPath($element, $stream),
            $element instanceof TextElement => $this->paintTextElement($element, $stream),
            $element instanceof Use_ => $this->paintUse($element, $stream),
            $element instanceof SvgImage => $this->paintImage($element, $stream),
            // `<defs>` and `<symbol>` are referenceable containers: they
            // never paint themselves at the document level. `<use>`
            // expands `<defs>` / `<symbol>` referents; `clip-path` on
            // a painted element pulls in a `<clipPath>`; `mask` pulls
            // in a `<mask>`. Skipping them here also skips their
            // nested shape children, which is what the spec wants
            // (SVG 2 §5.5 / §5.6 / §14.4 / §14.5).
            $element instanceof Defs,
            $element instanceof Symbol,
            $element instanceof ClipPath,
            $element instanceof Mask => null,
            // `<g>` and any other container fall through here — the
            // recursive walk still descends into their children.
            default => $this->paintChildren($element, $stream),
        };
    }

    private function paintUse(Use_ $use, ContentStream $stream): void
    {
        if ($this->document === null) {
            return;
        }
        $referent = $use->resolve($this->document);
        if ($referent === null) {
            return;
        }
        // SVG 2 §5.6 — the `<use>`'s `x` / `y` translate the referent's
        // coordinate system. Width / height overrides on `<symbol>`
        // referents resolve through viewBox-to-viewport mapping; that
        // mapping needs target-rectangle context we don't have until
        // 3R, so 3Q honours the translation only.
        $x = $use->x();
        $y = $use->y();
        if ($x === 0.0 && $y === 0.0) {
            $this->paintUseReferent($referent, $stream);
            return;
        }
        $stream->saveGraphicsState();
        $stream->concatMatrix(1.0, 0.0, 0.0, 1.0, $x, $y);
        $this->paintUseReferent($referent, $stream);
        $stream->restoreGraphicsState();
    }

    /**
     * `<symbol>` referents have their children painted directly per
     * SVG 2 §5.5 — referencing them via `<use>` is the only way they
     * paint at all. Everything else routes through normal dispatch.
     */
    private function paintUseReferent(Element $referent, ContentStream $stream): void
    {
        if ($referent instanceof Symbol) {
            $this->paintChildren($referent, $stream);
            return;
        }
        $this->paintElement($referent, $stream);
    }

    private function paintImage(SvgImage $image, ContentStream $stream): void
    {
        if ($this->writer === null || $this->page === null) {
            return;
        }
        $href = $image->href();
        if ($href === null) {
            return;
        }
        // 3Q: filesystem hrefs only. `data:` and `http(s)://` go through
        // a resource-loader gate that lands with the html-to-pdf 1L work;
        // until that's available the painter refuses anything that
        // doesn't look like a local path so an SVG can't trigger an
        // unwanted file read or network fetch.
        if (str_contains($href, '://') || str_starts_with($href, 'data:')) {
            return;
        }
        // Resolve intrinsic source dimensions before registering so we
        // can fall back to them when the SVG omits width / height.
        // `ImageParser::parse` is cheap (header-only read) and
        // bypassing the writer keeps this self-contained.
        try {
            $info = ImageParser::parse($href);
        } catch (\Throwable) {
            return;
        }
        try {
            $resourceName = $this->writer->addImage($href, $this->page);
        } catch (\Throwable) {
            // Missing file / unparseable bytes / unsupported format —
            // SVG 2 §12.6's "no image available" outcome is to paint
            // nothing.
            return;
        }

        $x = $image->x();
        $y = $image->y();
        $w = $image->width();
        $h = $image->height();
        $intrinsicW = (float) $info->width;
        $intrinsicH = (float) $info->height;
        // SVG 2 §12.6 fallback ladder for missing width / height: when
        // one dimension is given the other is scaled to preserve the
        // intrinsic aspect ratio; when both are absent the intrinsic
        // dimensions are used directly. A zero-size intrinsic image
        // still paints nothing.
        if ($intrinsicW <= 0.0 || $intrinsicH <= 0.0) {
            return;
        }
        if ($w === null && $h === null) {
            $w = $intrinsicW;
            $h = $intrinsicH;
        } elseif ($w === null) {
            // height set, width follows the intrinsic aspect.
            $w = ($h ?? 0.0) * ($intrinsicW / $intrinsicH);
        } elseif ($h === null) {
            $h = $w * ($intrinsicH / $intrinsicW);
        }
        if ($w <= 0.0 || $h <= 0.0) {
            return;
        }

        // PDF Do paints the image inside a unit square at (0, 0) → (1, 1).
        // The transformation matrix translates + scales it to the SVG
        // rectangle. Y is flipped so the image's top-left lands at
        // (x, y) — PDF's image space is y-down within the unit square,
        // SVG's image element is y-down too, so the flip cancels them
        // out and the image renders right-side-up at the SVG-stated
        // position.
        $stream->saveGraphicsState();
        $stream->concatMatrix($w, 0.0, 0.0, -$h, $x, $y + $h);
        $stream->doXObject($resourceName);
        $stream->restoreGraphicsState();
    }

    private function paintTextElement(TextElement $text, ContentStream $stream): void
    {
        // Without writer + page references the resolver can't register a
        // font, so the text silently drops. Matches the same standalone
        // posture the gradient painter uses at 3O.
        if ($this->fontResolver === null) {
            return;
        }
        $content = self::collectTextContent($text);
        if ($content === '') {
            return;
        }

        // SVG 2 default fill for `<text>` is black; the existing
        // applyFillPaint path covers that, but we apply it *before*
        // entering the text object so the colour persists across the
        // Tj sequence (PDF text objects share the page graphics state).
        $fill = $text->fill();
        if (!($fill instanceof None_)) {
            $this->applyFillPaint($fill, $text, $stream);
        }

        $font = $this->fontResolver->resolve(
            $text->fontFamily(),
            $text->fontWeight(),
            $text->fontStyle(),
        );
        $size = $text->fontSize() ?? 16.0;

        // SVG 2 §11.6 list-valued positioning. `<text x="10 20 30">ABC</text>`
        // positions each glyph individually. When any list has > 1 entry
        // the painter walks character-by-character; otherwise it falls
        // through to the cheaper single-Tj path. Single-value rotate
        // also takes the per-glyph code path so the rotation lands in
        // the text matrix.
        $xList = $text->x();
        $yList = $text->y();
        $dxList = $text->dx();
        $dyList = $text->dy();
        $rotateList = $text->rotate();
        $perGlyph = count($xList) > 1
            || count($yList) > 1
            || $dxList !== []
            || $dyList !== []
            || $rotateList !== [];

        $stream->beginText()->setFont($font, $size);
        if (!$perGlyph) {
            $x = $xList[0] ?? 0.0;
            $y = $yList[0] ?? 0.0;
            if ($this->compensateTextFlip) {
                // Under an outer Y-flip CTM (`SvgRenderer::draw` applies
                // one), `Td` would render glyphs upside-down. Setting Tm
                // with `d = -1` flips text space so the combined
                // `Tm · CTM` cancels the outer flip and glyphs render
                // upright at the SVG-stated baseline.
                $stream->setTextMatrix(1.0, 0.0, 0.0, -1.0, $x, $y);
            } else {
                $stream->moveTextPosition($x, $y);
            }
            $stream->showText($content);
        } else {
            $this->paintTextPerGlyph($content, $xList, $yList, $dxList, $dyList, $rotateList, $stream);
        }
        $stream->endText();
    }

    /**
     * Per-glyph positioning per SVG 2 §11.6: walk content character-by-
     * character, emit one `Tm` per explicitly-positioned glyph, then
     * batch the remaining characters as a single `Tj` so their natural
     * advance handles the trailing positioning.
     *
     * Sticky semantics: when a glyph specifies `x[i]` but not `y[i]`
     * (or vice-versa), the unspecified component carries over from the
     * previous glyph's position. SVG 2 actually defines this as "the
     * previous glyph's effective position" which requires knowing the
     * per-glyph advance — we don't have font metrics here at 3R+5, so
     * we use the last explicit value instead. The result is correct
     * for the common case where `x` and `y` have matching lengths.
     *
     * `dx[i]` / `dy[i]` are additive deltas applied to the resolved
     * `(stickyX, stickyY)` — SVG 2 §11.6 specifies them as relative
     * offsets from the glyph's natural position. Without font metrics
     * "natural position" collapses to "sticky position", so we treat
     * dx/dy as deltas from sticky. The deltas accumulate into sticky
     * so subsequent glyphs without their own dx/dy inherit the shift,
     * matching the common renderer behaviour for stacked offsets like
     * super/subscript adjustments.
     *
     * @param list<float> $xList
     * @param list<float> $yList
     * @param list<float> $dxList
     * @param list<float> $dyList
     * @param list<float> $rotateList
     */
    private function paintTextPerGlyph(
        string $content,
        array $xList,
        array $yList,
        array $dxList,
        array $dyList,
        array $rotateList,
        ContentStream $stream,
    ): void {
        $chars = mb_str_split($content);
        if ($chars === []) {
            return;
        }
        $explicitCount = max(
            count($xList),
            count($yList),
            count($dxList),
            count($dyList),
            count($rotateList),
        );
        $stickyX = $xList[0] ?? 0.0;
        $stickyY = $yList[0] ?? 0.0;
        $stickyRotate = $rotateList[0] ?? 0.0;

        $emitted = 0;
        foreach ($chars as $i => $char) {
            if ($i >= $explicitCount) {
                break;
            }
            $stickyX = $xList[$i] ?? $stickyX;
            $stickyY = $yList[$i] ?? $stickyY;
            $stickyRotate = $rotateList[$i] ?? $stickyRotate;
            if (isset($dxList[$i])) {
                $stickyX += $dxList[$i];
            }
            if (isset($dyList[$i])) {
                $stickyY += $dyList[$i];
            }

            $this->emitTextMatrix($stickyX, $stickyY, $stickyRotate, $stream);
            $stream->showText($char);
            $emitted++;
        }
        if ($emitted < count($chars)) {
            // Remaining glyphs ride the auto-advance from the last
            // positioned glyph — emit them as a single `Tj` so the
            // PDF reader inter-glyph kerning still applies.
            $stream->showText(implode('', array_slice($chars, $emitted)));
        }
    }

    /**
     * Set the text matrix for a single positioned glyph. Combines the
     * per-glyph rotation with the optional outer-flip compensation
     * established by `SvgRenderer::draw`. Algebra:
     *
     *  Without flip: Tm = T(x,y) · R(θ) = [cosθ sinθ -sinθ cosθ x y]
     *  With    flip: Tm = T(x,y) · F · R(θ) = [cosθ sinθ sinθ -cosθ x y]
     *
     * where `F = [1 0 0 -1 0 0]` is the y-axis flip.
     */
    private function emitTextMatrix(float $x, float $y, float $rotateDegrees, ContentStream $stream): void
    {
        $rad = deg2rad($rotateDegrees);
        $cos = cos($rad);
        $sin = sin($rad);
        if ($this->compensateTextFlip) {
            $stream->setTextMatrix($cos, $sin, $sin, -$cos, $x, $y);
        } else {
            $stream->setTextMatrix($cos, $sin, -$sin, $cos, $x, $y);
        }
    }

    /**
     * Concatenate all `Phpdftk\Svg\Text` (data) descendants in document
     * order. SVG 2's whitespace handling is complex (xml:space="preserve"
     * vs the default collapse); 3P keeps things simple by emitting the
     * source bytes verbatim and leaving whitespace policy to the future
     * cascade-aware text painter.
     */
    private static function collectTextContent(Element $element): string
    {
        $out = '';
        foreach ($element->children as $child) {
            if ($child instanceof TextNode) {
                $out .= $child->data;
                continue;
            }
            if ($child instanceof Element) {
                $out .= self::collectTextContent($child);
            }
        }
        return $out;
    }

    private function paintPath(Path $path, ContentStream $stream): void
    {
        $commands = $path->d()->commands;
        if ($commands === []) {
            return;
        }
        $state = new PathPainterState();
        foreach ($commands as $command) {
            $this->emitPathCommand($command, $stream, $state);
        }
        $this->applyFillAndStroke($path, $stream);
    }

    private function emitPathCommand(
        PathCommand $command,
        ContentStream $stream,
        PathPainterState $state,
    ): void {
        match (true) {
            $command instanceof MoveTo => $this->emitMoveTo($command, $stream, $state),
            $command instanceof LineTo => $this->emitLineTo($command, $stream, $state),
            $command instanceof HorizontalLineTo => $this->emitHorizontalLineTo($command, $stream, $state),
            $command instanceof VerticalLineTo => $this->emitVerticalLineTo($command, $stream, $state),
            $command instanceof CurveTo => $this->emitCurveTo($command, $stream, $state),
            $command instanceof SmoothCurveTo => $this->emitSmoothCurveTo($command, $stream, $state),
            $command instanceof QuadraticCurveTo => $this->emitQuadraticCurveTo($command, $stream, $state),
            $command instanceof SmoothQuadraticCurveTo => $this->emitSmoothQuadraticCurveTo($command, $stream, $state),
            $command instanceof ArcTo => $this->emitArcTo($command, $stream, $state),
            $command instanceof ClosePath => $this->emitClosePath($stream, $state),
            // Sealed-via-convention: 3rd-party impls of `PathCommand` are
            // not part of the SVG spec, so we no-op silently rather than
            // throw — same posture the parser uses for unknown content.
            default => null,
        };
    }

    private function emitMoveTo(MoveTo $cmd, ContentStream $stream, PathPainterState $state): void
    {
        [$x, $y] = $this->resolvePoint($cmd->x, $cmd->y, $cmd->absolute, $state);
        $stream->moveTo($x, $y);
        $state->moveTo($x, $y);
    }

    private function emitLineTo(LineTo $cmd, ContentStream $stream, PathPainterState $state): void
    {
        [$x, $y] = $this->resolvePoint($cmd->x, $cmd->y, $cmd->absolute, $state);
        $stream->lineTo($x, $y);
        $state->lineTo($x, $y);
    }

    private function emitHorizontalLineTo(
        HorizontalLineTo $cmd,
        ContentStream $stream,
        PathPainterState $state,
    ): void {
        $x = $cmd->absolute ? $cmd->x : $state->currentX + $cmd->x;
        $stream->lineTo($x, $state->currentY);
        $state->lineTo($x, $state->currentY);
    }

    private function emitVerticalLineTo(
        VerticalLineTo $cmd,
        ContentStream $stream,
        PathPainterState $state,
    ): void {
        $y = $cmd->absolute ? $cmd->y : $state->currentY + $cmd->y;
        $stream->lineTo($state->currentX, $y);
        $state->lineTo($state->currentX, $y);
    }

    private function emitCurveTo(CurveTo $cmd, ContentStream $stream, PathPainterState $state): void
    {
        [$x1, $y1] = $this->resolvePoint($cmd->x1, $cmd->y1, $cmd->absolute, $state);
        [$x2, $y2] = $this->resolvePoint($cmd->x2, $cmd->y2, $cmd->absolute, $state);
        [$x, $y] = $this->resolvePoint($cmd->x, $cmd->y, $cmd->absolute, $state);
        $stream->curveTo($x1, $y1, $x2, $y2, $x, $y);
        $state->currentX = $x;
        $state->currentY = $y;
        $state->recordCubicControl($x2, $y2);
    }

    private function emitSmoothCurveTo(
        SmoothCurveTo $cmd,
        ContentStream $stream,
        PathPainterState $state,
    ): void {
        [$x1, $y1] = $state->reflectedCubicControl();
        [$x2, $y2] = $this->resolvePoint($cmd->x2, $cmd->y2, $cmd->absolute, $state);
        [$x, $y] = $this->resolvePoint($cmd->x, $cmd->y, $cmd->absolute, $state);
        $stream->curveTo($x1, $y1, $x2, $y2, $x, $y);
        $state->currentX = $x;
        $state->currentY = $y;
        $state->recordCubicControl($x2, $y2);
    }

    /**
     * PDF has no native quadratic curve operator. Lift to cubic via the
     * standard `C1 = P0 + 2/3·(P1-P0)`, `C2 = P2 + 2/3·(P1-P2)` formula —
     * mathematically exact, no approximation error.
     */
    private function emitQuadraticCurveTo(
        QuadraticCurveTo $cmd,
        ContentStream $stream,
        PathPainterState $state,
    ): void {
        [$qx, $qy] = $this->resolvePoint($cmd->x1, $cmd->y1, $cmd->absolute, $state);
        [$ex, $ey] = $this->resolvePoint($cmd->x, $cmd->y, $cmd->absolute, $state);
        $this->emitQuadraticAsCubic($state->currentX, $state->currentY, $qx, $qy, $ex, $ey, $stream);
        $state->currentX = $ex;
        $state->currentY = $ey;
        $state->recordQuadraticControl($qx, $qy);
    }

    private function emitSmoothQuadraticCurveTo(
        SmoothQuadraticCurveTo $cmd,
        ContentStream $stream,
        PathPainterState $state,
    ): void {
        [$qx, $qy] = $state->reflectedQuadraticControl();
        [$ex, $ey] = $this->resolvePoint($cmd->x, $cmd->y, $cmd->absolute, $state);
        $this->emitQuadraticAsCubic($state->currentX, $state->currentY, $qx, $qy, $ex, $ey, $stream);
        $state->currentX = $ex;
        $state->currentY = $ey;
        $state->recordQuadraticControl($qx, $qy);
    }

    private function emitArcTo(ArcTo $cmd, ContentStream $stream, PathPainterState $state): void
    {
        [$endX, $endY] = $this->resolvePoint($cmd->x, $cmd->y, $cmd->absolute, $state);
        $segments = ArcToCubic::convert(
            $state->currentX,
            $state->currentY,
            $cmd->rx,
            $cmd->ry,
            $cmd->xAxisRotation,
            $cmd->largeArc,
            $cmd->sweep,
            $endX,
            $endY,
        );
        if ($segments === []) {
            // Degenerate: zero-length or zero-radius. Per SVG 2 §9.5.1,
            // an arc with a zero radius is rendered as a straight line.
            if ($cmd->rx === 0.0 || $cmd->ry === 0.0) {
                $stream->lineTo($endX, $endY);
                $state->lineTo($endX, $endY);
            }
            return;
        }
        foreach ($segments as $segment) {
            $stream->curveTo(
                $segment['x1'],
                $segment['y1'],
                $segment['x2'],
                $segment['y2'],
                $segment['x'],
                $segment['y'],
            );
        }
        $state->currentX = $endX;
        $state->currentY = $endY;
        $state->clearControlPoints();
    }

    private function emitClosePath(ContentStream $stream, PathPainterState $state): void
    {
        $stream->closePath();
        $state->closeSubpath();
    }

    private function emitQuadraticAsCubic(
        float $p0x,
        float $p0y,
        float $p1x,
        float $p1y,
        float $p2x,
        float $p2y,
        ContentStream $stream,
    ): void {
        $twoThirds = 2.0 / 3.0;
        $c1x = $p0x + $twoThirds * ($p1x - $p0x);
        $c1y = $p0y + $twoThirds * ($p1y - $p0y);
        $c2x = $p2x + $twoThirds * ($p1x - $p2x);
        $c2y = $p2y + $twoThirds * ($p1y - $p2y);
        $stream->curveTo($c1x, $c1y, $c2x, $c2y, $p2x, $p2y);
    }

    /**
     * @return array{float, float}
     */
    private function resolvePoint(float $x, float $y, bool $absolute, PathPainterState $state): array
    {
        if ($absolute) {
            return [$x, $y];
        }
        return [$state->currentX + $x, $state->currentY + $y];
    }

    private function paintRect(Rect $rect, ContentStream $stream): void
    {
        if ($rect->width() <= 0.0 || $rect->height() <= 0.0) {
            return;
        }
        $stream->rectangle($rect->x(), $rect->y(), $rect->width(), $rect->height());
        $this->applyFillAndStroke($rect, $stream);
    }

    private function paintCircle(Circle $circle, ContentStream $stream): void
    {
        if ($circle->r() <= 0.0) {
            return;
        }
        $this->emitEllipsePath($stream, $circle->cx(), $circle->cy(), $circle->r(), $circle->r());
        $this->applyFillAndStroke($circle, $stream);
    }

    private function paintEllipse(Ellipse $ellipse, ContentStream $stream): void
    {
        $rx = $ellipse->rx();
        $ry = $ellipse->ry();
        if ($rx === null || $ry === null || $rx <= 0.0 || $ry <= 0.0) {
            return;
        }
        $this->emitEllipsePath($stream, $ellipse->cx(), $ellipse->cy(), $rx, $ry);
        $this->applyFillAndStroke($ellipse, $stream);
    }

    private function paintLine(Line $line, ContentStream $stream): void
    {
        // Lines never enclose an area; only stroke is meaningful. Skip
        // entirely when stroke resolves to no paint — emitting a stroke
        // op with no colour would otherwise draw a black line by
        // accident.
        $stroke = $line->stroke();
        if ($stroke === null || $stroke instanceof None_) {
            return;
        }
        if (!$this->applyStrokePaint($stroke, $line, $stream)) {
            return;
        }
        $stream->moveTo($line->x1(), $line->y1())
            ->lineTo($line->x2(), $line->y2())
            ->stroke();
    }

    private function paintPolyline(Polyline $polyline, ContentStream $stream): void
    {
        $points = $polyline->points();
        if (count($points) < 2) {
            return;
        }
        $this->emitPolyPath($stream, $points, closed: false);
        $this->applyFillAndStroke($polyline, $stream);
    }

    private function paintPolygon(Polygon $polygon, ContentStream $stream): void
    {
        $points = $polygon->points();
        if (count($points) < 3) {
            return;
        }
        $this->emitPolyPath($stream, $points, closed: true);
        $this->applyFillAndStroke($polygon, $stream);
    }

    /**
     * Standard 4-cubic-Bézier ellipse approximation. Maximum radial
     * error against the true ellipse is ~0.027 % — well below print
     * resolution for any reasonable PDF size.
     */
    private function emitEllipsePath(
        ContentStream $stream,
        float $cx,
        float $cy,
        float $rx,
        float $ry,
    ): void {
        $kx = $rx * self::KAPPA;
        $ky = $ry * self::KAPPA;
        $stream
            ->moveTo($cx + $rx, $cy)
            ->curveTo($cx + $rx, $cy + $ky, $cx + $kx, $cy + $ry, $cx, $cy + $ry)
            ->curveTo($cx - $kx, $cy + $ry, $cx - $rx, $cy + $ky, $cx - $rx, $cy)
            ->curveTo($cx - $rx, $cy - $ky, $cx - $kx, $cy - $ry, $cx, $cy - $ry)
            ->curveTo($cx + $kx, $cy - $ry, $cx + $rx, $cy - $ky, $cx + $rx, $cy)
            ->closePath();
    }

    /**
     * @param list<array{float, float}> $points
     */
    private function emitPolyPath(ContentStream $stream, array $points, bool $closed): void
    {
        $first = $points[0];
        $stream->moveTo($first[0], $first[1]);
        for ($i = 1, $n = count($points); $i < $n; $i++) {
            $stream->lineTo($points[$i][0], $points[$i][1]);
        }
        if ($closed) {
            $stream->closePath();
        }
    }

    /**
     * Resolve the element's fill and stroke and emit the right PDF
     * paint operator combination. Defaults follow SVG 2 §13.2.1 — black
     * fill, no stroke — so a bare `<rect width=… height=…/>` paints as
     * a filled black rectangle.
     */
    private function applyFillAndStroke(Element $element, ContentStream $stream): void
    {
        $fill = $element->fill();
        $stroke = $element->stroke();

        $hasFill = $this->applyFillPaint($fill, $element, $stream);
        $hasStroke = $this->applyStrokePaint($stroke, $element, $stream);

        $rule = $element->fillRule() ?? 'nonzero';

        if ($hasFill && $hasStroke) {
            $rule === 'evenodd' ? $stream->fillAndStrokeEvenOdd() : $stream->fillAndStroke();
            return;
        }
        if ($hasFill) {
            $rule === 'evenodd' ? $stream->fillEvenOdd() : $stream->fill();
            return;
        }
        if ($hasStroke) {
            $stream->stroke();
            return;
        }
        // Path constructed but nothing wants to paint it — discard so
        // we don't bake a leftover current-path into the graphics state.
        $stream->endPath();
    }

    /**
     * Configure the fill colour and report whether the element wants a
     * fill at all. Default (null paint) = SVG-spec black fill; explicit
     * `none` = no fill; `currentColor` resolves to black at 3K (the
     * cascade-resolved `color` lands later). Gradient/pattern `url(#…)`
     * is deferred to 3O.
     */
    private function applyFillPaint(?Paint $paint, Element $element, ContentStream $stream): bool
    {
        if ($paint instanceof None_) {
            return false;
        }
        if ($paint instanceof Url) {
            return $this->gradientPainter?->applyAsFill($paint->id, $element, $stream) ?? false;
        }
        if ($paint instanceof SolidColor) {
            $this->setFillColor($stream, $paint->color);
            return true;
        }
        // null or CurrentColor → SVG 2 §13.2.1 default of black.
        $stream->setFillColorRGB(0.0, 0.0, 0.0);
        return true;
    }

    /**
     * Configure the stroke colour and report whether the element wants
     * to stroke. Default (null paint) = SVG-spec "no stroke"; explicit
     * `none` = no stroke.
     */
    private function applyStrokePaint(?Paint $paint, Element $element, ContentStream $stream): bool
    {
        if ($paint === null || $paint instanceof None_) {
            return false;
        }
        if ($paint instanceof Url) {
            return $this->gradientPainter?->applyAsStroke($paint->id, $element, $stream) ?? false;
        }
        if ($paint instanceof SolidColor) {
            $this->setStrokeColor($stream, $paint->color);
            return true;
        }
        // CurrentColor → black at 3K.
        $stream->setStrokeColorRGB(0.0, 0.0, 0.0);
        return true;
    }

    private function setFillColor(ContentStream $stream, ColorInterface $color): void
    {
        match (true) {
            $color instanceof RgbColor => $stream->setFillRgbColor($color),
            $color instanceof CmykColor => $stream->setFillCmykColor($color),
            $color instanceof GrayColor => $stream->setFillGrayColor($color),
            default => $stream->setFillColorRGB(0.0, 0.0, 0.0),
        };
    }

    private function setStrokeColor(ContentStream $stream, ColorInterface $color): void
    {
        match (true) {
            $color instanceof RgbColor => $stream->setStrokeRgbColor($color),
            $color instanceof CmykColor => $stream->setStrokeCmykColor($color),
            $color instanceof GrayColor => $stream->setStrokeGrayColor($color),
            default => $stream->setStrokeColorRGB(0.0, 0.0, 0.0),
        };
    }
}
