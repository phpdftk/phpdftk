<?php

declare(strict_types=1);

namespace Phpdftk\SvgToPdf\Path;

use Phpdftk\Svg\Path\ArcTo;
use Phpdftk\Svg\Path\ClosePath;
use Phpdftk\Svg\Path\CurveTo;
use Phpdftk\Svg\Path\HorizontalLineTo;
use Phpdftk\Svg\Path\LineTo;
use Phpdftk\Svg\Path\MoveTo;
use Phpdftk\Svg\Path\PathData;
use Phpdftk\Svg\Path\QuadraticCurveTo;
use Phpdftk\Svg\Path\SmoothCurveTo;
use Phpdftk\Svg\Path\SmoothQuadraticCurveTo;
use Phpdftk\Svg\Path\VerticalLineTo;

/**
 * SVG 2 §11.6.2 — the vertices at which a shape's `marker-start`,
 * `marker-mid` and `marker-end` markers are placed, and the angle each
 * one is drawn at.
 *
 * A vertex is every point at which one path segment ends and the next
 * begins, INCLUDING the points either side of a `moveto` that opens a
 * new subpath and the point a `closepath` returns to. The first vertex
 * of the whole shape takes `marker-start`, the last takes `marker-end`,
 * and every other vertex takes `marker-mid` — the split is per SHAPE,
 * not per subpath, which is why `m … m …` puts mid markers on both
 * sides of the gap rather than a second start/end pair.
 *
 * The angle at a vertex is derived from the two tangent directions that
 * meet there:
 *
 *   - `marker-start` uses the OUTGOING direction only.
 *   - `marker-end` uses the INCOMING direction only.
 *   - `marker-mid` uses the bisector of the two. The bisector is
 *     computed on the two angles, not on the summed vectors, and the
 *     incoming angle is lifted by a full turn when the pair straddles
 *     the ±180° cut so a shallow join never flips the marker by 180°
 *     (the classic WebKit 193015 discontinuity).
 *
 * The tangent of a curve segment is taken from its control polygon: the
 * first control point gives the outgoing direction at its start, the
 * last control point the incoming direction at its end. Elliptical arcs
 * are lowered to cubics first, exactly as the painter lowers them, so
 * the tangents agree with the geometry that actually gets drawn.
 */
final class MarkerVertices
{
    /** Below this, a segment is a point rather than a direction. */
    private const float EPSILON = 1.0e-9;

    /**
     * A shape's marker vertices, in path order.
     *
     * @return list<MarkerVertex>
     */
    public static function ofPathData(PathData $data): array
    {
        return self::ofSegments(self::flatten($data));
    }

    /**
     * The marker vertices of a point-list shape — `<line>`,
     * `<polyline>` and `<polygon>`. A `<polygon>` is a `<polyline>`
     * plus the implicit `closepath` SVG 2 §10.7 gives it, which adds
     * the vertex at the closing corner.
     *
     * @param list<array{float, float}> $points
     * @return list<MarkerVertex>
     */
    public static function ofPoints(array $points, bool $closed): array
    {
        if ($points === []) {
            return [];
        }
        $segments = [['move', [$points[0]]]];
        $count = count($points);
        for ($i = 1; $i < $count; $i++) {
            $segments[] = ['line', [$points[$i]]];
        }
        if ($closed) {
            $segments[] = ['close', []];
        }
        return self::ofSegments($segments);
    }

    /**
     * Lower a parsed `d` attribute to the absolute `move` / `line` /
     * `quad` / `cubic` / `close` segment list the vertex walk consumes.
     *
     * The relative-to-absolute and smooth-reflection rules here mirror
     * {@see PathLengthMeasure} and the painter's own emitters: a
     * reflected control point is only seeded by a curve of the SAME
     * degree (SVG 2 §9.3.6 / §9.3.7), so `L 10 10 S …` reflects about
     * the current point rather than a stale cubic control.
     *
     * @return list<array{string, list<array{float, float}>}>
     */
    private static function flatten(PathData $data): array
    {
        /** @var list<array{string, list<array{float, float}>}> $segments */
        $segments = [];
        $cx = $cy = 0.0;
        $startX = $startY = 0.0;
        $lastCubicControlX = $lastCubicControlY = null;
        $lastQuadControlX = $lastQuadControlY = null;

        foreach ($data->commands as $command) {
            $isCubic = false;
            $isQuad = false;

            switch (true) {
                case $command instanceof MoveTo:
                    [$x, $y] = self::resolve($command->absolute, $command->x, $command->y, $cx, $cy);
                    $segments[] = ['move', [[$x, $y]]];
                    $cx = $startX = $x;
                    $cy = $startY = $y;
                    break;

                case $command instanceof LineTo:
                    [$x, $y] = self::resolve($command->absolute, $command->x, $command->y, $cx, $cy);
                    $segments[] = ['line', [[$x, $y]]];
                    $cx = $x;
                    $cy = $y;
                    break;

                case $command instanceof HorizontalLineTo:
                    $x = $command->absolute ? $command->x : $cx + $command->x;
                    $segments[] = ['line', [[$x, $cy]]];
                    $cx = $x;
                    break;

                case $command instanceof VerticalLineTo:
                    $y = $command->absolute ? $command->y : $cy + $command->y;
                    $segments[] = ['line', [[$cx, $y]]];
                    $cy = $y;
                    break;

                case $command instanceof CurveTo:
                    [$x1, $y1] = self::resolve($command->absolute, $command->x1, $command->y1, $cx, $cy);
                    [$x2, $y2] = self::resolve($command->absolute, $command->x2, $command->y2, $cx, $cy);
                    [$x, $y] = self::resolve($command->absolute, $command->x, $command->y, $cx, $cy);
                    $segments[] = ['cubic', [[$x1, $y1], [$x2, $y2], [$x, $y]]];
                    $lastCubicControlX = $x2;
                    $lastCubicControlY = $y2;
                    $cx = $x;
                    $cy = $y;
                    $isCubic = true;
                    break;

                case $command instanceof SmoothCurveTo:
                    $x1 = $lastCubicControlX === null ? $cx : 2.0 * $cx - $lastCubicControlX;
                    $y1 = $lastCubicControlY === null ? $cy : 2.0 * $cy - $lastCubicControlY;
                    [$x2, $y2] = self::resolve($command->absolute, $command->x2, $command->y2, $cx, $cy);
                    [$x, $y] = self::resolve($command->absolute, $command->x, $command->y, $cx, $cy);
                    $segments[] = ['cubic', [[$x1, $y1], [$x2, $y2], [$x, $y]]];
                    $lastCubicControlX = $x2;
                    $lastCubicControlY = $y2;
                    $cx = $x;
                    $cy = $y;
                    $isCubic = true;
                    break;

                case $command instanceof QuadraticCurveTo:
                    [$x1, $y1] = self::resolve($command->absolute, $command->x1, $command->y1, $cx, $cy);
                    [$x, $y] = self::resolve($command->absolute, $command->x, $command->y, $cx, $cy);
                    $segments[] = ['quad', [[$x1, $y1], [$x, $y]]];
                    $lastQuadControlX = $x1;
                    $lastQuadControlY = $y1;
                    $cx = $x;
                    $cy = $y;
                    $isQuad = true;
                    break;

                case $command instanceof SmoothQuadraticCurveTo:
                    $x1 = $lastQuadControlX === null ? $cx : 2.0 * $cx - $lastQuadControlX;
                    $y1 = $lastQuadControlY === null ? $cy : 2.0 * $cy - $lastQuadControlY;
                    [$x, $y] = self::resolve($command->absolute, $command->x, $command->y, $cx, $cy);
                    $segments[] = ['quad', [[$x1, $y1], [$x, $y]]];
                    $lastQuadControlX = $x1;
                    $lastQuadControlY = $y1;
                    $cx = $x;
                    $cy = $y;
                    $isQuad = true;
                    break;

                case $command instanceof ArcTo:
                    [$x, $y] = self::resolve($command->absolute, $command->x, $command->y, $cx, $cy);
                    $cubics = ArcToCubic::convert(
                        $cx,
                        $cy,
                        $command->rx,
                        $command->ry,
                        $command->xAxisRotation,
                        $command->largeArc,
                        $command->sweep,
                        $x,
                        $y,
                    );
                    if ($cubics === []) {
                        // A degenerate arc (zero radius, or start ==
                        // end) is a straight line per SVG 2 §9.5.4 —
                        // the painter draws one, so it contributes the
                        // same single vertex here.
                        $segments[] = ['line', [[$x, $y]]];
                    } else {
                        // An `A` is ONE path segment (SVG 2 §11.6.2),
                        // so it contributes ONE vertex — at its end.
                        // The painter lowers it to up to four cubics
                        // for PDF, but emitting those as separate
                        // segments here planted a spurious mid marker
                        // at every quarter-arc boundary. Only the
                        // tangents at the two ENDS matter, so keep the
                        // first control point and the last one and
                        // drop the subdivision.
                        $last = $cubics[count($cubics) - 1];
                        $segments[] = ['cubic', [
                            [$cubics[0]['x1'], $cubics[0]['y1']],
                            [$last['x2'], $last['y2']],
                            [$last['x'], $last['y']],
                        ]];
                    }
                    $cx = $x;
                    $cy = $y;
                    break;

                case $command instanceof ClosePath:
                    $segments[] = ['close', [[$startX, $startY]]];
                    $cx = $startX;
                    $cy = $startY;
                    break;

                default:
                    // Third-party `PathCommand` implementations are not
                    // part of the spec; the painter no-ops on them, so
                    // they place no markers either.
                    break;
            }

            if (!$isCubic) {
                $lastCubicControlX = $lastCubicControlY = null;
            }
            if (!$isQuad) {
                $lastQuadControlX = $lastQuadControlY = null;
            }
        }

        return $segments;
    }

    /**
     * The vertex walk itself.
     *
     * Each segment first CLOSES the vertex the previous segment landed
     * on — its own first point supplies that vertex's OUTGOING tangent —
     * and then opens the next one. The last vertex is emitted after the
     * loop.
     *
     * Two SVG 2 §11.6.2 rules shape the ends of a subpath:
     *
     *  - A `moveto` is not a segment and carries no direction. "For a
     *    vertex that starts a subpath the 'in' direction is the same as
     *    the 'out' direction, and for a vertex that ends a subpath the
     *    'out' direction is the same as the 'in'." So the jump between
     *    subpaths never bends a marker. Treating the `moveto` as an
     *    ordinary segment instead bisected the real tangent against the
     *    jump vector and rotated the markers either side off the path.
     *
     *  - A CLOSED subpath has no ends: its first and last vertex are
     *    the same point and it is a genuine corner, so the first
     *    vertex's incoming direction is the closing segment and the
     *    last vertex's outgoing direction is the first segment. The
     *    closing segment is only known once `Z` is reached, which is
     *    why the subpath's opening vertex is patched afterwards rather
     *    than written correctly first time.
     *
     * @param list<array{string, list<array{float, float}>}> $segments
     * @return list<MarkerVertex>
     */
    private static function ofSegments(array $segments): array
    {
        if ($segments === []) {
            return [];
        }
        /** @var list<MarkerVertex> $vertices */
        $vertices = [];
        $originX = $originY = 0.0;
        $subpathStartX = $subpathStartY = 0.0;
        $inSlopeX = $inSlopeY = 0.0;
        $startsSubpath = true;
        // Bookkeeping for the closed-subpath corner: which vertex opened
        // the current subpath, the direction it left on, and whether a
        // `Z` has brought us back to it.
        $openingVertex = null;
        $openingOutX = $openingOutY = 0.0;
        $justClosed = false;
        $index = 0;

        foreach ($segments as [$type, $points]) {
            if ($points === []) {
                $points = [[$subpathStartX, $subpathStartY]];
            }
            // A `Z` onto the point the subpath already ends on draws
            // no segment, so it adds no vertex either. Emitting one
            // put a SECOND marker on the closing corner — a mid on top
            // of the end marker — whenever the last curve already
            // landed back on the start point.
            $degenerateClose = $type === 'close'
                && abs($subpathStartX - $originX) <= self::EPSILON
                && abs($subpathStartY - $originY) <= self::EPSILON;
            if ($index > 0 && !$degenerateClose) {
                // A `moveto` ends the subpath the previous segment was
                // building, so that vertex has no outgoing tangent of
                // its own and reuses its incoming one.
                $endsSubpath = $type === 'move';
                $outSlopeX = $endsSubpath ? $inSlopeX : $points[0][0] - $originX;
                $outSlopeY = $endsSubpath ? $inSlopeY : $points[0][1] - $originY;
                $thisInX = $startsSubpath ? $outSlopeX : $inSlopeX;
                $thisInY = $startsSubpath ? $outSlopeY : $inSlopeY;
                if ($justClosed && $endsSubpath && $openingVertex !== null) {
                    $outSlopeX = $openingOutX;
                    $outSlopeY = $openingOutY;
                    $vertices[$openingVertex] = self::withInSlope(
                        $vertices[$openingVertex],
                        $thisInX,
                        $thisInY,
                    );
                }
                if ($startsSubpath) {
                    $openingVertex = count($vertices);
                    $openingOutX = $outSlopeX;
                    $openingOutY = $outSlopeY;
                }
                $vertices[] = new MarkerVertex(
                    $originX,
                    $originY,
                    $thisInX,
                    $thisInY,
                    $outSlopeX,
                    $outSlopeY,
                );
                $startsSubpath = $endsSubpath;
                $justClosed = false;
            }
            switch ($type) {
                case 'move':
                    $subpathStartX = $points[0][0];
                    $subpathStartY = $points[0][1];
                    $originX = $points[0][0];
                    $originY = $points[0][1];
                    break;
                case 'line':
                    $inSlopeX = $points[0][0] - $originX;
                    $inSlopeY = $points[0][1] - $originY;
                    $originX = $points[0][0];
                    $originY = $points[0][1];
                    break;
                case 'quad':
                    $inSlopeX = $points[1][0] - $points[0][0];
                    $inSlopeY = $points[1][1] - $points[0][1];
                    $originX = $points[1][0];
                    $originY = $points[1][1];
                    break;
                case 'cubic':
                    $inSlopeX = $points[2][0] - $points[1][0];
                    $inSlopeY = $points[2][1] - $points[1][1];
                    $originX = $points[2][0];
                    $originY = $points[2][1];
                    break;
                case 'close':
                    // A `Z` whose subpath already ends on its start
                    // point draws no segment, so it supplies no
                    // direction either and the tangent the last real
                    // segment arrived on stands. Taking the zero vector
                    // as the incoming direction instead read as 0 deg
                    // and spun every marker on a closed Bezier or arc
                    // loop a quarter turn off the outline.
                    if (abs($subpathStartX - $originX) > self::EPSILON
                        || abs($subpathStartY - $originY) > self::EPSILON
                    ) {
                        $inSlopeX = $subpathStartX - $originX;
                        $inSlopeY = $subpathStartY - $originY;
                    }
                    $originX = $subpathStartX;
                    $originY = $subpathStartY;
                    $justClosed = true;
                    break;
            }
            $index++;
        }

        // The final vertex ends its subpath, so its outgoing direction
        // is its incoming one — unless a `Z` closed that subpath, in
        // which case it is the corner the subpath opened on. A path
        // that is nothing but a `moveto` has neither direction and gets
        // the zero vector, which `atan2` reads as 0 degrees: the same
        // unrotated marker a browser draws.
        $lastOutX = $inSlopeX;
        $lastOutY = $inSlopeY;
        if ($justClosed && $openingVertex !== null) {
            $lastOutX = $openingOutX;
            $lastOutY = $openingOutY;
            $vertices[$openingVertex] = self::withInSlope(
                $vertices[$openingVertex],
                $inSlopeX,
                $inSlopeY,
            );
        }
        $vertices[] = new MarkerVertex(
            $originX,
            $originY,
            $inSlopeX,
            $inSlopeY,
            $lastOutX,
            $lastOutY,
        );

        return $vertices;
    }

    /** The same vertex with a different incoming tangent. */
    private static function withInSlope(MarkerVertex $vertex, float $x, float $y): MarkerVertex
    {
        return new MarkerVertex(
            $vertex->x,
            $vertex->y,
            $x,
            $y,
            $vertex->outSlopeX,
            $vertex->outSlopeY,
        );
    }

    /** @return array{float, float} */
    private static function resolve(
        bool $absolute,
        float $x,
        float $y,
        float $cx,
        float $cy,
    ): array {
        return $absolute ? [$x, $y] : [$cx + $x, $cy + $y];
    }
}
