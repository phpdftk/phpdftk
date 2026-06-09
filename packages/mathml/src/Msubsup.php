<?php

declare(strict_types=1);

namespace Phpdftk\Mathml;

/**
 * `<msubsup>` — combined sub-and-superscript (MathML Core §3.3.6).
 *
 * Exactly three element children in document order: `BASE SUB SUP`.
 * Both scripts attach at the same x position (base right edge); the
 * construct's total width is `base + max(subWidth, supWidth)`.
 */
final class Msubsup extends Element
{
    public function __construct()
    {
        parent::__construct('msubsup');
    }
}
