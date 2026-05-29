<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Path;

/** `L x y` / `l dx dy` — draw a line to the given point. */
final class LineTo implements PathCommand
{
    public function __construct(
        public readonly bool $absolute,
        public readonly float $x,
        public readonly float $y,
    ) {}
}
