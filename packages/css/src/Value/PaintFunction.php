<?php

declare(strict_types=1);

namespace Phpdftk\Css\Value;

/**
 * CSS Painting API Level 1 — `paint(<name> [, <arg>]*)`.
 * References a CSS Houdini paint worklet by name; the worklet
 * is JS-side, so for print PDF rendering this is purely
 * declarative preservation — the cascade keeps the call shape
 * so external tooling can react to it, but no paint runs.
 *
 *   background: paint(myCheckerboard, blue, 16px);
 */
final readonly class PaintFunction extends Value
{
    /** @param list<Value> $arguments */
    public function __construct(
        public string $name,
        public array $arguments = [],
    ) {}

    public function toCss(): string
    {
        if ($this->arguments === []) {
            return sprintf('paint(%s)', $this->name);
        }
        $args = implode(', ', array_map(static fn(Value $v): string => $v->toCss(), $this->arguments));
        return sprintf('paint(%s, %s)', $this->name, $args);
    }
}
