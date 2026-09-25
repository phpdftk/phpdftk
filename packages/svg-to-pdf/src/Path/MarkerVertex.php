<?php

declare(strict_types=1);

namespace Phpdftk\SvgToPdf\Path;

/**
 * One SVG 2 §11.6.2 marker vertex: where a marker sits, plus the two
 * tangent directions that meet there.
 *
 * Both directions are always populated. SVG 2 fills in the missing one
 * at a path's ends — "for a vertex that starts a subpath the 'in'
 * direction is the same as the 'out' direction, and for a vertex that
 * ends a subpath the 'out' direction is the same as the 'in'" — and
 * {@see MarkerVertices} applies that rule while walking, so there is no
 * such thing here as a half-defined vertex. That is what lets
 * `marker-start`, `marker-mid` and `marker-end` share ONE angle rule
 * (the bisector) instead of each needing its own: on an open path the
 * two directions coincide at the ends and the bisector degenerates to
 * the single tangent, while on a CLOSED subpath the start/end point is
 * a genuine corner and the bisector is what browsers draw there.
 */
final class MarkerVertex
{
    public function __construct(
        public readonly float $x,
        public readonly float $y,
        public readonly float $inSlopeX,
        public readonly float $inSlopeY,
        public readonly float $outSlopeX,
        public readonly float $outSlopeY,
    ) {}

    /** The incoming tangent direction, in degrees. */
    public function inAngle(): float
    {
        return rad2deg(atan2($this->inSlopeY, $this->inSlopeX));
    }

    /** The outgoing tangent direction, in degrees. */
    public function outAngle(): float
    {
        return rad2deg(atan2($this->outSlopeY, $this->outSlopeX));
    }

    /**
     * The direction `orient="auto"` points the marker in: the bisector
     * of the incoming and outgoing tangents (SVG 2 §11.6.3).
     *
     * Taken as the angle swept FROM the incoming direction TO the
     * outgoing one, halved — not as the arithmetic mean of two
     * `atan2` results, which is discontinuous across the ±180° cut and
     * would flip a marker end-for-end at a shallow join.
     *
     * When that sweep is half a turn or more the two tangents are on
     * opposite sides, so the marker faces along the OTHER bisector,
     * the one that splits the side the path actually turns through.
     * The exactly-180° case is what makes the difference visible: a
     * path that doubles back on itself (`M50,0 v50 z`) has tangents
     * 90° and -90°, whose mean is 0° — pointing the marker along a
     * direction the path never travels. The swept bisector gives 180°,
     * which is what browsers draw.
     */
    public function angle(): float
    {
        $in = $this->inAngle();
        $delta = fmod($this->outAngle() - $in, 360.0);
        if ($delta < 0.0) {
            $delta += 360.0;
        }
        $bisector = $in + $delta / 2.0;
        return $delta >= 180.0 ? $bisector + 180.0 : $bisector;
    }
}
