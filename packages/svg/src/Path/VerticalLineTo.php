<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Path;

/** `V y` / `v dy` — vertical line, current x unchanged. */
final class VerticalLineTo implements PathCommand
{
    public function __construct(
        public readonly bool $absolute,
        public readonly float $y,
    ) {}
}
