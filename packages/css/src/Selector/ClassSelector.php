<?php

declare(strict_types=1);

namespace Phpdftk\Css\Selector;

/**
 * Class selector per Selectors 4 §6.6: `.classname`. Specificity (0, 1, 0).
 */
final readonly class ClassSelector extends SimpleSelector
{
    public function __construct(public string $className) {}

    public function specificity(): Specificity
    {
        return new Specificity(0, 1, 0);
    }

    public function toString(): string
    {
        return '.' . $this->className;
    }
}
