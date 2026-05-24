<?php

declare(strict_types=1);

namespace Phpdftk\Css\Token;

/**
 * `#main`, `#FF00AA`, `#-rule`, etc. The type-flag distinguishes id-shaped
 * hashes (CSS Syntax 3 §4.3.1) from generic-shaped: id-shaped are valid for
 * id selectors, generic hashes for things like color hex literals.
 */
final readonly class HashToken extends Token
{
    public function __construct(public string $value, public HashTokenType $type = HashTokenType::Unrestricted) {}
}
