<?php

declare(strict_types=1);

namespace Phpdftk\Css\Selector;

/**
 * ID selector per Selectors 4 §7: `#identifier`. Specificity (1, 0, 0).
 */
final readonly class IdSelector extends SimpleSelector
{
    public function __construct(public string $id) {}

    public function specificity(): Specificity
    {
        return new Specificity(1, 0, 0);
    }

    public function toString(): string
    {
        return '#' . $this->id;
    }
}
