<?php

declare(strict_types=1);

namespace Phpdftk\Mathml;

/**
 * `<msup>` — superscript construct (MathML Core §3.3.6).
 *
 * Exactly two element children: base then superscript.
 * `<msup>BASE SUPERSCRIPT</msup>` renders the base followed by a
 * smaller superscript at upper-right. Canonical example: x².
 *
 * Painter uses 0.7 × main font size for the superscript and raises
 * by 0.5em above the surrounding baseline.
 */
final class Msup extends Element
{
    public function __construct()
    {
        parent::__construct('msup');
    }
}
