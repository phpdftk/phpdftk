<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Filter;

/**
 * SVG 2 Filter Effects §15.16 — `<feTile>`. Tiles the input
 * across the filter region using infinite repetition.
 *
 * No primitive-specific attributes beyond the base subregion +
 * `in` / `result` accessors inherited from FilterPrimitive.
 */
final class FeTile extends FilterPrimitive
{
    public function __construct()
    {
        parent::__construct('feTile');
    }
}
