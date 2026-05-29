<?php

declare(strict_types=1);

namespace Phpdftk\SvgToPdf;

use Phpdftk\Color\CmykColor;
use Phpdftk\Color\ColorInterface;
use Phpdftk\Color\GrayColor;
use Phpdftk\Color\RgbColor;
use Phpdftk\Pdf\Core\Content\ContentStream;
use Phpdftk\Pdf\Writer\Page;
use Phpdftk\Pdf\Writer\PdfWriter;
use Phpdftk\SvgToPdf\Gradient\GradientPainter;
use Phpdftk\Svg\Element;
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
    ): void {
        $this->page = $page;
        $this->gradientPainter = $page !== null && $writer !== null
            ? new GradientPainter($writer, $page, $document)
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
            $this->gradientPainter = null;
        }
    }

    private ?Page $page = null;
    private ?GradientPainter $gradientPainter = null;

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
        //
        // Painting the same shape with all defaults stays a one-shot
        // op stream — no overhead when none of the above is set.
        $transform = $element->transform();
        $opacityGs = $this->resolveOpacityState($element);
        $needsStrokeParams = $this->needsStrokeParams($element);
        $needsWrap = $transform !== null || $opacityGs !== null || $needsStrokeParams;

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
        $this->dispatchElement($element, $stream);
        $stream->restoreGraphicsState();
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
            // `<g>` and any other container fall through here — the
            // recursive walk still descends into their children.
            default => $this->paintChildren($element, $stream),
        };
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
