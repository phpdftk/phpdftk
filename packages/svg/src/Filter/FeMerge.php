<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Filter;

/**
 * SVG 2 Filter Effects §15.11 — `<feMerge>` stacks any number of
 * `<feMergeNode>` children, alpha-compositing each on top of the
 * previous one. Used for drop-shadow + content chains:
 *
 *   <feMerge>
 *     <feMergeNode in="shadow"/>
 *     <feMergeNode in="SourceGraphic"/>
 *   </feMerge>
 *
 * The Node children are read via the typed accessors on
 * {@see FeMergeNode}.
 */
final class FeMerge extends FilterPrimitive
{
    public function __construct()
    {
        parent::__construct('feMerge');
    }
}
