<?php

declare(strict_types=1);

namespace Phpdftk\Css\Value;

final readonly class CalcLeaf extends CalcExpression
{
    public function __construct(public Value $value) {}

    public function toCss(): string
    {
        return $this->value->toCss();
    }
}
