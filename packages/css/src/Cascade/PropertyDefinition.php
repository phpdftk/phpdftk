<?php

declare(strict_types=1);

namespace Phpdftk\Css\Cascade;

use Phpdftk\Css\Value\Value;

/**
 * Per-property metadata consumed by the cascade and the computed-value
 * resolution. Each CSS property registered with the engine ships an
 * immutable definition stating its initial value, whether it inherits, and
 * whether it accepts the special `inherit` / `initial` / `unset` keywords
 * (every property does, but we keep the flag for forward compat).
 *
 * Phase 1D.3 ships a small subset — color, font, box-model essentials —
 * sufficient for the MVP invoice fixture. Additional properties slot in by
 * appending registrations in `PropertyRegistry::default()`.
 */
final readonly class PropertyDefinition
{
    public function __construct(
        public string $name,
        public Value $initial,
        public bool $inherits = false,
    ) {}
}
