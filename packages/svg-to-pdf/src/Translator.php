<?php

declare(strict_types=1);

namespace Phpdftk\SvgToPdf;

use Phpdftk\Color\CmykColor;
use Phpdftk\Color\ColorInterface;
use Phpdftk\Color\GrayColor;
use Phpdftk\Color\RgbColor;
use Phpdftk\Pdf\Core\Content\ContentStream;
use Phpdftk\Svg\Element;
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

    public function paint(SvgDocument $document, ContentStream $stream): void
    {
        $this->paintChildren($document, $stream);
    }

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
        match (true) {
            $element instanceof Rect => $this->paintRect($element, $stream),
            $element instanceof Circle => $this->paintCircle($element, $stream),
            $element instanceof Ellipse => $this->paintEllipse($element, $stream),
            $element instanceof Line => $this->paintLine($element, $stream),
            $element instanceof Polyline => $this->paintPolyline($element, $stream),
            $element instanceof Polygon => $this->paintPolygon($element, $stream),
            default => $this->paintChildren($element, $stream),
        };
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
        if (!$this->applyStrokePaint($stroke, $stream)) {
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

        $hasFill = $this->applyFillPaint($fill, $stream);
        $hasStroke = $this->applyStrokePaint($stroke, $stream);

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
    private function applyFillPaint(?Paint $paint, ContentStream $stream): bool
    {
        if ($paint instanceof None_) {
            return false;
        }
        if ($paint instanceof Url) {
            // Gradient / pattern fill — painter 3O.
            return false;
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
    private function applyStrokePaint(?Paint $paint, ContentStream $stream): bool
    {
        if ($paint === null || $paint instanceof None_) {
            return false;
        }
        if ($paint instanceof Url) {
            return false;
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
