<?php

declare(strict_types=1);

namespace Phpdftk\Mathml;

/**
 * `<mtext>` — generic prose token (MathML Core §3.2.7).
 *
 * Renders in upright text font (no italic-by-default heuristic). Used
 * for explanatory labels (`<mtext>where</mtext>`) inside math
 * expressions.
 */
final class Mtext extends Element
{
    public function __construct()
    {
        parent::__construct('mtext');
    }
}
