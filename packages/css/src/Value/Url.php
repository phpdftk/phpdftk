<?php

declare(strict_types=1);

namespace Phpdftk\Css\Value;

final readonly class Url extends Value
{
    public function __construct(public string $url) {}

    public function toCss(): string
    {
        return 'url("' . str_replace('"', '\\"', $this->url) . '")';
    }
}
