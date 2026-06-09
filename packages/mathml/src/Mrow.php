<?php

declare(strict_types=1);

namespace Phpdftk\Mathml;

/**
 * `<mrow>` — grouping container (MathML Core §3.3.1).
 *
 * Lays children out horizontally on a single baseline. Used to group
 * children logically for scripts (`<msup><mrow>x+1</mrow><mn>2</mn>`)
 * and for visual grouping (`<mrow><mo>(</mo>… <mo>)</mo></mrow>`).
 * Painter walks children left-to-right; no extra spacing beyond what
 * the inner elements declare.
 */
final class Mrow extends Element
{
    public function __construct()
    {
        parent::__construct('mrow');
    }
}
