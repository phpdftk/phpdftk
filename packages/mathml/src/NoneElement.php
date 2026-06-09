<?php

declare(strict_types=1);

namespace Phpdftk\Mathml;

/**
 * `<none/>` — placeholder for an absent script position inside an
 * {@see Mmultiscripts} pair (MathML Core §3.3.6.2).
 *
 * Used in script pairs where only one side is present, e.g.
 * `<mn>1</mn><none/>` is a subscript-only pair (no superscript at
 * that pair's slot). The painter treats `<none/>` as a zero-width
 * skip — the pair still occupies layout-grid space for alignment
 * with surrounding pairs, but contributes no glyphs.
 *
 * Named with a `…Element` suffix to avoid colliding with PHP's
 * (potential future) reserved word `null`/`None`-likes; the class's
 * `localName` is the canonical `'none'`.
 */
final class NoneElement extends Element
{
    public function __construct()
    {
        parent::__construct('none');
    }
}
