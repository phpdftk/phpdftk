<?php

declare(strict_types=1);

namespace Phpdftk\Mathml;

/**
 * `<mtr>` — table row (MathML Core §3.3.7).
 *
 * Container for {@see Mtd} cells. Only meaningful as a direct child of
 * {@see Mtable}; the painter scans `Mtable`'s typed children for `Mtr`
 * instances to discover row structure.
 *
 * A loose `<mtr>` outside `<mtable>` round-trips through the parser
 * unchanged but the painter walks its children inline.
 */
final class Mtr extends Element
{
    public function __construct()
    {
        parent::__construct('mtr');
    }
}
