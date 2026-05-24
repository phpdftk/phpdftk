<?php

declare(strict_types=1);

namespace Phpdftk\Css\Value;

/**
 * A CSS identifier used as a value — `auto`, `none`, `inherit`, `block`,
 * etc. Always lower-cased: CSS identifiers are case-insensitive, the value
 * parser normalises them at intake.
 */
final readonly class Keyword extends Value
{
    public function __construct(public string $name) {}

    public function toCss(): string
    {
        return $this->name;
    }
}
