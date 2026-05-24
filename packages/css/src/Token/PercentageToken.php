<?php

declare(strict_types=1);

namespace Phpdftk\Css\Token;

final readonly class PercentageToken extends Token
{
    public function __construct(public float $value) {}
}
