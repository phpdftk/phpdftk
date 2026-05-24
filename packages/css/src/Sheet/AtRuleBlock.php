<?php

declare(strict_types=1);

namespace Phpdftk\Css\Sheet;

/**
 * Body of a nested at-rule like `@media` or `@font-face`. The contents are
 * either child rules (@media's nested style rules) or declarations
 * (@font-face's `src`/`font-family`/...). The parser picks per-token
 * based on the shape of each item.
 *
 * @phpstan-type RuleOrDecl Rule|Declaration
 */
final readonly class AtRuleBlock
{
    /** @param list<Rule|Declaration> $contents */
    public function __construct(public array $contents) {}
}
