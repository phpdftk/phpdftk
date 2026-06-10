<?php

declare(strict_types=1);

namespace Phpdftk\Mathml;

/**
 * `<mlabeledtr>` — labelled table row (legacy MathML 3 §3.5.6).
 *
 * MathML Core dropped `<mlabeledtr>` from the element set but
 * real content still ships with it (equation-numbered displayed
 * equations from older converters). The spec for content that
 * uses it: render the row as if it were `<mtr>` with the FIRST
 * child removed; the removed child is the label which the user
 * agent may position in a margin.
 *
 * v1 painter behaviour: treat the element as an `<mtr>` that
 * starts at the second child (i.e. drop the label cell). Future
 * work can render the label in the margin once we have a
 * page-level coordinate system around math islands.
 */
final class Mlabeledtr extends Mtr
{
    public function __construct()
    {
        Element::__construct('mlabeledtr');
    }
}
