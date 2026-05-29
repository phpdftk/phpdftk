<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Path;

/** `M x y` / `m dx dy` — start a new sub-path at the given point. */
final class MoveTo implements PathCommand
{
    public function __construct(
        public readonly bool $absolute,
        public readonly float $x,
        public readonly float $y,
    ) {}
}
