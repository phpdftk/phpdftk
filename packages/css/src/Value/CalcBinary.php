<?php

declare(strict_types=1);

namespace Phpdftk\Css\Value;

final readonly class CalcBinary extends CalcExpression
{
    public function __construct(
        public CalcExpression $left,
        public CalcOp $op,
        public CalcExpression $right,
    ) {}

    public function toCss(): string
    {
        return '(' . $this->left->toCss() . ' ' . $this->op->value . ' ' . $this->right->toCss() . ')';
    }
}
