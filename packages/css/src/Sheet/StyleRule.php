<?php

declare(strict_types=1);

namespace Phpdftk\Css\Sheet;

use Phpdftk\Css\Selector\SelectorList;

/**
 * A "qualified rule" with a selector prelude and a block of declarations.
 * The `selectors` SelectorList carries the raw selector text in Phase 1A.3;
 * its `selectors` array fills in when the selector engine ships in 1D.
 */
final readonly class StyleRule extends Rule
{
    /** @param list<Declaration> $declarations */
    public function __construct(
        public SelectorList $selectors,
        public array $declarations,
    ) {}
}
