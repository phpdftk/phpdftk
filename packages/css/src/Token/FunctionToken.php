<?php

declare(strict_types=1);

namespace Phpdftk\Css\Token;

/**
 * Emitted when an identifier is immediately followed by '(' — e.g. `rgb(`.
 * The function name (without the paren) is in {@see $name}.
 */
final readonly class FunctionToken extends Token
{
    public function __construct(public string $name) {}
}
