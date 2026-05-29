<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Path;

/** `H x` / `h dx` — horizontal line, current y unchanged. */
final class HorizontalLineTo implements PathCommand
{
    public function __construct(
        public readonly bool $absolute,
        public readonly float $x,
    ) {}
}
