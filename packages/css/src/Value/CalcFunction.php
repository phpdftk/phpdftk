<?php

declare(strict_types=1);

namespace Phpdftk\Css\Value;

/**
 * Top-level math-function flavours per CSS Values 4 §10. Each takes a
 * different argument shape; the value parser routes accordingly.
 */
enum CalcFunction: string
{
    case Calc = 'calc';
    case Min = 'min';
    case Max = 'max';
    case Clamp = 'clamp';
    case Round = 'round';
    case Mod = 'mod';
    case Rem = 'rem';
    case Sin = 'sin';
    case Cos = 'cos';
    case Tan = 'tan';
    case Asin = 'asin';
    case Acos = 'acos';
    case Atan = 'atan';
    case Atan2 = 'atan2';
    case Pow = 'pow';
    case Sqrt = 'sqrt';
    case Hypot = 'hypot';
    case Log = 'log';
    case Exp = 'exp';
    case Abs = 'abs';
    case Sign = 'sign';
}
