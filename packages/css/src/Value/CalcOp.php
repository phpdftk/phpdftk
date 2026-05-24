<?php

declare(strict_types=1);

namespace Phpdftk\Css\Value;

enum CalcOp: string
{
    case Add = '+';
    case Sub = '-';
    case Mul = '*';
    case Div = '/';
}
