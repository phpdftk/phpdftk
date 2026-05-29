<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Shape;

use Phpdftk\Svg\Element;

/**
 * SVG `<circle>` element per SVG 2 §10.5. `cx` / `cy` default to 0 when
 * absent; `r` defaults to 0 (renders nothing, per spec).
 */
final class Circle extends Element
{
    public function __construct()
    {
        parent::__construct('circle');
    }

    public function cx(): float
    {
        return $this->parseLengthOrZero('cx');
    }

    public function cy(): float
    {
        return $this->parseLengthOrZero('cy');
    }

    public function r(): float
    {
        return $this->parseLengthOrZero('r');
    }
}
