<?php

declare(strict_types=1);

namespace Phpdftk\Geometry;

/**
 * 2D point in PDF user-space coordinates (1 unit = 1/72 inch).
 */
final class Point
{
    public function __construct(
        public readonly float $x,
        public readonly float $y,
    ) {}
}
