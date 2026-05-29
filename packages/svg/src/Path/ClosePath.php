<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Path;

/**
 * `Z` / `z` — close the current sub-path with a straight line back to its
 * starting point. The two case forms are semantically equivalent per SVG 2;
 * `$absolute` is preserved only so round-tripping is faithful.
 */
final class ClosePath implements PathCommand
{
    public function __construct(public readonly bool $absolute) {}
}
