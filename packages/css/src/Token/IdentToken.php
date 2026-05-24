<?php

declare(strict_types=1);

namespace Phpdftk\Css\Token;

final readonly class IdentToken extends Token
{
    public function __construct(public string $value) {}
}
