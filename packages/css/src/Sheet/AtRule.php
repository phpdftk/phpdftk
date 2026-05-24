<?php

declare(strict_types=1);

namespace Phpdftk\Css\Sheet;

/**
 * `@name <prelude> { … }` or `@name <prelude>;` — the at-rule form.
 *
 * `prelude` is the raw text between the at-keyword and the block / semicolon
 * (whitespace-collapsed); consumers like the cascade re-parse it into typed
 * structures (media queries, page selectors, etc.) using helpers in the
 * relevant subsystem.
 *
 * `block` is null for declaration-only at-rules (`@charset`, `@import`,
 * `@namespace`); a populated {@see AtRuleBlock} otherwise.
 */
final readonly class AtRule extends Rule
{
    public function __construct(
        public string $name,
        public string $prelude,
        public ?AtRuleBlock $block,
    ) {}
}
