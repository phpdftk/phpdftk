<?php

declare(strict_types=1);

namespace Phpdftk\Css\Token;

/**
 * A numeric literal with an `Integer`/`Number` type flag per CSS Syntax 3
 * §4.3.3. Integers parse without a fractional part; numbers may include
 * decimals or an exponent.
 */
final readonly class NumberToken extends Token
{
    public function __construct(public float $value, public NumberTokenType $type) {}
}
