<?php

declare(strict_types=1);

namespace Phpdftk\Css\Selector;

/**
 * Selectors-4 combinators per §5.
 *
 * - Descendant: whitespace between compounds
 * - Child: `>`
 * - NextSibling: `+`
 * - SubsequentSibling: `~`
 * - Column: `||` (Selectors 4 only; matches column-table relationships)
 */
enum Combinator: string
{
    case Descendant = ' ';
    case Child = '>';
    case NextSibling = '+';
    case SubsequentSibling = '~';
    case Column = '||';
}
