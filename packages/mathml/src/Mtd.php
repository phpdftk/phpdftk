<?php

declare(strict_types=1);

namespace Phpdftk\Mathml;

/**
 * `<mtd>` — table cell (MathML Core §3.3.7).
 *
 * Container that may hold any presentation-markup subtree (tokens,
 * rows, fractions, scripts, even nested tables). Treated like a
 * transparent `<mrow>` for content, but its bounding box drives the
 * column-width / row-height max in the parent {@see Mtable}'s layout.
 *
 * The `columnspan` and `rowspan` attributes are not honoured by the
 * v1 painter — cells span exactly one column and one row regardless.
 * They round-trip through the parser.
 */
final class Mtd extends Element
{
    public function __construct()
    {
        parent::__construct('mtd');
    }
}
