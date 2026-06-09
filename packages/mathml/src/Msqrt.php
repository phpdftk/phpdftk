<?php

declare(strict_types=1);

namespace Phpdftk\Mathml;

/**
 * `<msqrt>` — square-root construct (MathML Core §3.3.4).
 *
 * Container element: any number of children flow inside the radical
 * symbol. Visually rendered as a √ glyph followed by the content
 * with a horizontal vinculum (overline) covering it.
 *
 * Painter scope: the v1 renderer draws the vinculum only — the √
 * glyph itself is deferred because the standard Type1 Times-Roman
 * font ships without U+221A in StandardEncoding. Adding the glyph
 * means either pulling in Symbol font or pinning a math font; both
 * are separable follow-ups.
 */
final class Msqrt extends Element
{
    public function __construct()
    {
        parent::__construct('msqrt');
    }
}
