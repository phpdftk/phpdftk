<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Shape;

use Phpdftk\Svg\Element;

/**
 * SVG `<polygon>` element per SVG 2 §10.9. Shares the `points` attribute
 * grammar with `<polyline>`; the difference (auto-closing stroke) is a
 * painter concern, not a parser one.
 */
final class Polygon extends Element
{
    public function __construct()
    {
        parent::__construct('polygon');
    }

    /** @return list<array{float, float}> */
    public function points(): array
    {
        return self::parsePoints($this->getAttribute('points'));
    }
}
