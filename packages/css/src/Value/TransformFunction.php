<?php

declare(strict_types=1);

namespace Phpdftk\Css\Value;

abstract readonly class TransformFunction
{
    abstract public function toCss(): string;
}
