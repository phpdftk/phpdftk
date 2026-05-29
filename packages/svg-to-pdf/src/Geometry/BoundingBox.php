<?php

declare(strict_types=1);

namespace Phpdftk\SvgToPdf\Geometry;

use Phpdftk\Svg\Element;
use Phpdftk\Svg\Shape\Circle;
use Phpdftk\Svg\Shape\Ellipse;
use Phpdftk\Svg\Shape\Line;
use Phpdftk\Svg\Shape\Polygon;
use Phpdftk\Svg\Shape\Polyline;
use Phpdftk\Svg\Shape\Rect;

/**
 * Axis-aligned bounding box used by `objectBoundingBox`-mode gradients
 * (SVG 2 §13.6.5). Returns `null` for elements whose bbox would require
 * walking the path AST or doing the kind of geometric work the 3O scope
 * defers — the gradient painter then falls back to skipping the fill,
 * matching SVG 2's "invalid → no paint" rule.
 */
final class BoundingBox
{
    /**
     * @return array{minX: float, minY: float, width: float, height: float}|null
     */
    public static function compute(Element $element): ?array
    {
        if ($element instanceof Rect) {
            $w = $element->width();
            $h = $element->height();
            return $w <= 0.0 || $h <= 0.0
                ? null
                : ['minX' => $element->x(), 'minY' => $element->y(), 'width' => $w, 'height' => $h];
        }
        if ($element instanceof Circle) {
            $r = $element->r();
            if ($r <= 0.0) {
                return null;
            }
            return [
                'minX' => $element->cx() - $r,
                'minY' => $element->cy() - $r,
                'width' => 2.0 * $r,
                'height' => 2.0 * $r,
            ];
        }
        if ($element instanceof Ellipse) {
            $rx = $element->rx();
            $ry = $element->ry();
            if ($rx === null || $ry === null || $rx <= 0.0 || $ry <= 0.0) {
                return null;
            }
            return [
                'minX' => $element->cx() - $rx,
                'minY' => $element->cy() - $ry,
                'width' => 2.0 * $rx,
                'height' => 2.0 * $ry,
            ];
        }
        if ($element instanceof Line) {
            $minX = min($element->x1(), $element->x2());
            $minY = min($element->y1(), $element->y2());
            $maxX = max($element->x1(), $element->x2());
            $maxY = max($element->y1(), $element->y2());
            return ['minX' => $minX, 'minY' => $minY, 'width' => $maxX - $minX, 'height' => $maxY - $minY];
        }
        if ($element instanceof Polyline || $element instanceof Polygon) {
            $points = $element->points();
            if ($points === []) {
                return null;
            }
            $minX = $points[0][0];
            $minY = $points[0][1];
            $maxX = $minX;
            $maxY = $minY;
            foreach ($points as $p) {
                $minX = min($minX, $p[0]);
                $minY = min($minY, $p[1]);
                $maxX = max($maxX, $p[0]);
                $maxY = max($maxY, $p[1]);
            }
            return ['minX' => $minX, 'minY' => $minY, 'width' => $maxX - $minX, 'height' => $maxY - $minY];
        }
        // `<path>` would require walking the command AST and tracking
        // curve extrema — out of scope at 3O. The painter falls back to
        // no fill in that case.
        return null;
    }
}
