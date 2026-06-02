<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Filter;

/**
 * SVG 2 Filter Effects §15.12 — `<feOffset dx dy>`. Translates
 * the input by `(dx, dy)` in the filter's coordinate space.
 * Used heavily in drop-shadow chains together with SourceAlpha
 * + feGaussianBlur.
 */
final class FeOffset extends FilterPrimitive
{
    public function __construct()
    {
        parent::__construct('feOffset');
    }

    public function dx(): float
    {
        return (float) ($this->getAttribute('dx') ?? 0);
    }

    public function dy(): float
    {
        return (float) ($this->getAttribute('dy') ?? 0);
    }
}
