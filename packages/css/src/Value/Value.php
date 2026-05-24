<?php

declare(strict_types=1);

namespace Phpdftk\Css\Value;

/**
 * Base for typed CSS values per CSS Values and Units Module 4. Every value
 * surfaced via the `ComputedStyle` accessors is one of these — no untyped
 * strings or generic maps in the public surface.
 *
 * `toCss()` produces a string round-trippable through the tokenizer + value
 * parser: serialize(parse(x)) === serialize(parse(serialize(parse(x)))).
 */
abstract readonly class Value
{
    abstract public function toCss(): string;
}
