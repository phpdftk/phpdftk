<?php

declare(strict_types=1);

namespace Phpdftk\Css\Sheet;

use Phpdftk\Css\Value\Value;

/**
 * A single property-value pair within a style or declaration block.
 *
 * The property name is always lower-cased; the value parser handles the
 * value side. `!important` is captured as a separate flag rather than being
 * folded into the value, so the cascade can sort by it without re-parsing.
 */
final readonly class Declaration
{
    public function __construct(
        public string $property,
        public Value $value,
        public bool $important = false,
    ) {}
}
