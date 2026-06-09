<?php

declare(strict_types=1);

namespace Phpdftk\Mathml;

/**
 * `<mmultiscripts>` — multi-script attachment (MathML Core §3.3.6.2).
 *
 * Child structure:
 *
 *   base
 *   ( postsub postsup )*
 *   [ <mprescripts/>
 *     ( presub presup )* ]
 *
 * Where:
 *   - `base` is exactly one element.
 *   - Postscripts: zero or more `(sub, sup)` element pairs.
 *   - {@see Mprescripts} marker optionally splits pre- from post-
 *     scripts.
 *   - {@see NoneElement} stands in for an absent script position
 *     within a pair (e.g. `<mn>1</mn><none/>` is a sub-only pair).
 *
 * The first script pair after the base (or after `<mprescripts/>`)
 * is the innermost — closest to the base. Subsequent pairs stack
 * outward from the base. Postscripts stack to the right; prescripts
 * stack to the left.
 *
 * Painter scope: the v1 renderer handles arbitrary numbers of pairs
 * on both sides, recognises `<none/>` as a zero-width gap within a
 * pair, and falls back to inline `walkChildren` for malformed
 * markup (odd number of scripts on either side).
 */
final class Mmultiscripts extends Element
{
    public function __construct()
    {
        parent::__construct('mmultiscripts');
    }
}
