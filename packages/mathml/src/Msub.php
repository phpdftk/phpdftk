<?php

declare(strict_types=1);

namespace Phpdftk\Mathml;

/**
 * `<msub>` — subscript construct (MathML Core §3.3.6).
 *
 * Exactly two element children: base then subscript.
 * `<msub>BASE SUBSCRIPT</msub>` renders as the base followed by a
 * smaller subscript at lower-right.
 *
 * The painter approximates "smaller" as 0.7 × the main font size and
 * "lower" as 0.3em below the surrounding baseline. Real glyph-
 * derived positioning lands once the renderer learns to measure
 * its own output.
 */
final class Msub extends Element
{
    public function __construct()
    {
        parent::__construct('msub');
    }
}
