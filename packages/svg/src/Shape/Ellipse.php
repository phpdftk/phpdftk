<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Shape;

use Phpdftk\Svg\Element;

/**
 * SVG `<ellipse>` element per SVG 2 §10.6. `cx` / `cy` default to 0
 * when absent. `rx` / `ry` mirror each other when only one is given
 * — same fallback shape as `<rect>` corner radii.
 */
final class Ellipse extends Element
{
    public function __construct()
    {
        parent::__construct('ellipse');
    }

    public function cx(): float
    {
        return $this->parseLengthOrZero('cx');
    }

    public function cy(): float
    {
        return $this->parseLengthOrZero('cy');
    }

    public function rx(): ?float
    {
        if ($this->hasAttribute('rx')) {
            return $this->parseLengthOrZero('rx');
        }
        if ($this->hasAttribute('ry')) {
            return $this->parseLengthOrZero('ry');
        }
        return null;
    }

    public function ry(): ?float
    {
        if ($this->hasAttribute('ry')) {
            return $this->parseLengthOrZero('ry');
        }
        if ($this->hasAttribute('rx')) {
            return $this->parseLengthOrZero('rx');
        }
        return null;
    }
}
