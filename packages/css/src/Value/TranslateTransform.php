<?php

declare(strict_types=1);

namespace Phpdftk\Css\Value;

final readonly class TranslateTransform extends TransformFunction
{
    public function __construct(
        public Length|Percentage $x,
        public Length|Percentage $y,
        public ?Length $z = null,
    ) {}

    public function toCss(): string
    {
        if ($this->z !== null) {
            return sprintf('translate3d(%s, %s, %s)', $this->x->toCss(), $this->y->toCss(), $this->z->toCss());
        }
        $yCss = $this->y->toCss();
        if ($yCss === '0' || $yCss === '0px') {
            return sprintf('translate(%s)', $this->x->toCss());
        }
        return sprintf('translate(%s, %s)', $this->x->toCss(), $yCss);
    }
}
