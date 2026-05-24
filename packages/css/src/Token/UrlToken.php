<?php

declare(strict_types=1);

namespace Phpdftk\Css\Token;

/** `url(...)` with an unquoted body. Quoted bodies become FunctionToken('url') + StringToken. */
final readonly class UrlToken extends Token
{
    public function __construct(public string $value) {}
}
