<?php

declare(strict_types=1);

namespace Phpdftk\Css\Token;

/** A number followed by an identifier — e.g. `12px`, `1.5em`. */
final readonly class DimensionToken extends Token
{
    public function __construct(
        public float $value,
        public string $unit,
        public NumberTokenType $type = NumberTokenType::Number,
    ) {}
}
