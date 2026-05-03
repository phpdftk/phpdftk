<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Toolkit\Label;

/**
 * Page label numbering styles (ISO 32000-2 Table 163).
 */
enum LabelStyle: string
{
    case Arabic = 'D';
    case RomanLower = 'r';
    case RomanUpper = 'R';
    case AlphaLower = 'a';
    case AlphaUpper = 'A';
}
