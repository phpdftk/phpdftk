<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Gradient;

/**
 * SVG `<radialGradient>` per SVG 2 §13.7. Defines a gradient whose colour
 * stops vary radially from a focal point `(fx, fy)` to a circle of radius
 * `r` centred at `(cx, cy)`. SVG 2 added the optional `fr` focal radius
 * for proper inner-circle support.
 *
 * Accessors return null when the attribute is absent so the painter
 * applies the SVG 2 §13.7.5 defaults (`cx`/`cy`/`r` = 50%, focal point
 * coincident with centre).
 */
final class RadialGradient extends Gradient
{
    public function __construct()
    {
        parent::__construct('radialGradient');
    }

    public function cx(): ?float
    {
        return $this->parseOptionalLength('cx');
    }

    public function cy(): ?float
    {
        return $this->parseOptionalLength('cy');
    }

    /** Outer-circle radius. Non-negative; null otherwise. */
    public function r(): ?float
    {
        $v = $this->parseOptionalLength('r');
        return $v !== null && $v < 0.0 ? null : $v;
    }

    public function fx(): ?float
    {
        return $this->parseOptionalLength('fx');
    }

    public function fy(): ?float
    {
        return $this->parseOptionalLength('fy');
    }

    /** Focal-circle radius (SVG 2 addition). Non-negative; null otherwise. */
    public function fr(): ?float
    {
        $v = $this->parseOptionalLength('fr');
        return $v !== null && $v < 0.0 ? null : $v;
    }

    private function parseOptionalLength(string $attr): ?float
    {
        $raw = $this->getAttribute($attr);
        if ($raw === null) {
            return null;
        }
        if (preg_match(
            '/^\s*([+-]?(?:\d+\.?\d*|\.\d+)(?:[eE][+-]?\d+)?)\s*(%|[a-zA-Z]*)/',
            $raw,
            $m,
        ) !== 1) {
            return null;
        }
        $value = (float) $m[1];
        if ($m[2] !== '' && $m[2] !== '%') {
            // A gradient coordinate is a `<length>`, so an ABSOLUTE
            // unit suffix is meaningful: `x2="1in"` is 96 CSS px, which
            // in objectBoundingBox mode is 96 bounding-box widths, not
            // one. Throwing the suffix away read `1in` as `1`.
            // Font-relative units need context this accessor doesn't
            // have, so `absoluteUnitScale()` leaves them at 1.
            return $value * \Phpdftk\Svg\Element::absoluteUnitScale($m[2]);
        }
        // SVG 2 §13.7.5 — a percentage in objectBoundingBox mode (the default
        // gradientUnits) is a fraction of the bounding box, so `cx="50%"` ==
        // `0.5`. Normalise to the fraction the GradientPainter bbox mapping
        // expects (a `%` radius parsed as e.g. `50` blew the gradient far past
        // the shape).
        if ($m[2] === '%') {
            $value /= 100.0;
        }
        return $value;
    }
}
