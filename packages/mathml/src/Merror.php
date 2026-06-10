<?php

declare(strict_types=1);

namespace Phpdftk\Mathml;

/**
 * `<merror>` — error-flag container (MathML Core §3.7.1).
 *
 * Wraps a fragment of MathML that the producer wants to flag as
 * malformed. The Core default styling is a salmon background +
 * red text; user agents are allowed to substitute, but the
 * intent is "this is broken, render it so the reader notices".
 *
 * The element has no behavioural attributes beyond the standard
 * MathML set (`mathcolor`, `mathbackground`, etc.). The painter
 * decides how to surface the error visually.
 */
final class Merror extends Element
{
    public function __construct()
    {
        parent::__construct('merror');
    }
}
