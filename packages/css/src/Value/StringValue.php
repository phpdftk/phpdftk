<?php

declare(strict_types=1);

namespace Phpdftk\Css\Value;

/**
 * A CSS string value, e.g. `"comic sans"` in `font-family`. Named with the
 * `Value` suffix to avoid the PHP `string` keyword collision.
 */
final readonly class StringValue extends Value
{
    public function __construct(public string $value) {}

    public function toCss(): string
    {
        return '"' . str_replace('"', '\\"', $this->value) . '"';
    }
}
