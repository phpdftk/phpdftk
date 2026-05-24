<?php

declare(strict_types=1);

namespace Phpdftk\Css\Value;

/**
 * `var(--name, <fallback>)` — an unresolved reference to a custom property.
 *
 * Lives untouched in the value tree until the cascade resolves it: when a
 * computed style is being built for an element, the cascade walks up the
 * inheritance chain looking for `--name` and substitutes its value here.
 * If no chain entry has it, the fallback is used; if there's no fallback
 * either, the property becomes invalid at computed-value time and the
 * cascade falls back to the initial value.
 */
final readonly class CustomProperty extends Value
{
    public function __construct(public string $name, public ?Value $fallback = null) {}

    public function toCss(): string
    {
        if ($this->fallback !== null) {
            return sprintf('var(%s, %s)', $this->name, $this->fallback->toCss());
        }
        return sprintf('var(%s)', $this->name);
    }
}
