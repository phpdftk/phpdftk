<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Filter;

use Phpdftk\Svg\Element;

/**
 * SVG 2 Filter Effects §15.15.1 — `<feDistantLight>`. Direction
 * source for `<feDiffuseLighting>` / `<feSpecularLighting>`.
 * Infinitely far away; rays are parallel.
 *
 *   azimuth:    direction in the XY plane, degrees (default 0)
 *   elevation:  pitch above the XY plane, degrees (default 0)
 */
final class FeDistantLight extends Element
{
    public function __construct()
    {
        parent::__construct('feDistantLight');
    }

    public function azimuth(): float
    {
        return (float) ($this->getAttribute('azimuth') ?? 0);
    }

    public function elevation(): float
    {
        return (float) ($this->getAttribute('elevation') ?? 0);
    }
}
