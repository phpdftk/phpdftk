<?php

declare(strict_types=1);

namespace Phpdftk\Css\Token;

/** Single-character delimiter that didn't match any other token kind. */
final readonly class DelimToken extends Token
{
    public function __construct(public string $value) {}
}
