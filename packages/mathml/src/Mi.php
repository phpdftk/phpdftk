<?php

declare(strict_types=1);

namespace Phpdftk\Mathml;

/**
 * `<mi>` — identifier token (MathML Core §3.2.3).
 *
 * Painter defaults: single-character content renders italic to match
 * conventional mathematical notation (e.g. `<mi>x</mi>` → italic *x*);
 * multi-character content (`sin`, `log`) renders upright. The
 * Translator implements this heuristic per Core §3.2.3.
 */
final class Mi extends Element
{
    public function __construct()
    {
        parent::__construct('mi');
    }
}
