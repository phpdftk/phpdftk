<?php

declare(strict_types=1);

namespace Phpdftk\Mathml;

/**
 * `<mn>` — numeric literal token (MathML Core §3.2.4).
 *
 * Painter defaults: upright (non-italic) glyphs in the math font's
 * regular weight, regardless of context. Override via `mathvariant`.
 */
final class Mn extends Element
{
    public function __construct()
    {
        parent::__construct('mn');
    }
}
