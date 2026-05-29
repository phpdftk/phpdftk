<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Shape;

use Phpdftk\Svg\Element;

/**
 * SVG `<polyline>` element per SVG 2 §10.8. Difference from `<polygon>`
 * is purely paint-time: a polyline's stroke does not auto-close, a
 * polygon's does. Both share the same `points` attribute grammar.
 */
final class Polyline extends Element
{
    public function __construct()
    {
        parent::__construct('polyline');
    }

    /** @return list<array{float, float}> */
    public function points(): array
    {
        return self::parsePoints($this->getAttribute('points'));
    }
}
