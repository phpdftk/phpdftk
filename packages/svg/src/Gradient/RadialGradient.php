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
        if (preg_match('/^\s*([+-]?(?:\d+\.?\d*|\.\d+)(?:[eE][+-]?\d+)?)/', $raw, $m) !== 1) {
            return null;
        }
        return (float) $m[1];
    }
}
