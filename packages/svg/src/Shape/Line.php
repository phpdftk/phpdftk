<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Shape;

use Phpdftk\Svg\Element;

/**
 * SVG `<line>` element per SVG 2 §10.7. Each endpoint coordinate
 * defaults to 0 when its attribute is absent.
 */
final class Line extends Element
{
    public function __construct()
    {
        parent::__construct('line');
    }

    public function x1(): float
    {
        return $this->parseLengthOrZero('x1');
    }

    public function y1(): float
    {
        return $this->parseLengthOrZero('y1');
    }

    public function x2(): float
    {
        return $this->parseLengthOrZero('x2');
    }

    public function y2(): float
    {
        return $this->parseLengthOrZero('y2');
    }
}
