<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Filter;

use Phpdftk\Svg\Element;

/**
 * SVG 2 Filter Effects §15.15.3 — `<feSpotLight>`. Cone-shaped
 * source for `<feDiffuseLighting>` / `<feSpecularLighting>`.
 *
 *   x, y, z:                   position in filter coordinates
 *   pointsAtX/Y/Z:             aim point
 *   specularExponent:          tightness of the cone (default 1)
 *   limitingConeAngle:         outer half-angle in degrees;
 *                              null = no falloff
 */
final class FeSpotLight extends Element
{
    public function __construct()
    {
        parent::__construct('feSpotLight');
    }

    public function x(): float
    {
        return (float) ($this->getAttribute('x') ?? 0);
    }

    public function y(): float
    {
        return (float) ($this->getAttribute('y') ?? 0);
    }

    public function z(): float
    {
        return (float) ($this->getAttribute('z') ?? 0);
    }

    public function pointsAtX(): float
    {
        return (float) ($this->getAttribute('pointsAtX') ?? 0);
    }

    public function pointsAtY(): float
    {
        return (float) ($this->getAttribute('pointsAtY') ?? 0);
    }

    public function pointsAtZ(): float
    {
        return (float) ($this->getAttribute('pointsAtZ') ?? 0);
    }

    public function specularExponent(): float
    {
        return max(1.0, (float) ($this->getAttribute('specularExponent') ?? 1));
    }

    public function limitingConeAngle(): ?float
    {
        $v = $this->getAttribute('limitingConeAngle');
        return $v !== null ? (float) $v : null;
    }
}
