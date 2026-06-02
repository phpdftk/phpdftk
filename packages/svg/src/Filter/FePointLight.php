<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Filter;

use Phpdftk\Svg\Element;

/**
 * SVG 2 Filter Effects §15.15.2 — `<fePointLight>`. Position
 * source for `<feDiffuseLighting>` / `<feSpecularLighting>`.
 * Emits light isotropically from a single point.
 *
 *   x, y, z: position in filter coordinates (defaults 0)
 */
final class FePointLight extends Element
{
    public function __construct()
    {
        parent::__construct('fePointLight');
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
}
