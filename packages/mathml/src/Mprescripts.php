<?php

declare(strict_types=1);

namespace Phpdftk\Mathml;

/**
 * `<mprescripts/>` — boundary marker inside {@see Mmultiscripts}
 * (MathML Core §3.3.6.2). Splits the child list into post-scripts
 * (before the marker) and pre-scripts (after).
 *
 * Empty element — carries no children of its own. The painter scans
 * an `<mmultiscripts>`'s element children for an instance of this
 * class to find the boundary; everything before is appended to the
 * base on the right, everything after is prepended on the left.
 */
final class Mprescripts extends Element
{
    public function __construct()
    {
        parent::__construct('mprescripts');
    }
}
