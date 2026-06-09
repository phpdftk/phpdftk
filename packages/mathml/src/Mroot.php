<?php

declare(strict_types=1);

namespace Phpdftk\Mathml;

/**
 * `<mroot>` — n-th root construct (MathML Core §3.3.5).
 *
 * Exactly two element children: `<mroot>BASE INDEX</mroot>`. The base
 * goes under the radical symbol with a vinculum (overline) covering
 * it; the index sits to the upper-left at script size (~0.7em, raised
 * ~0.5em).
 *
 * Painter scope: same caveat as {@see Msqrt} — vinculum drawn,
 * √ glyph deferred to math-font integration.
 */
final class Mroot extends Element
{
    public function __construct()
    {
        parent::__construct('mroot');
    }
}
