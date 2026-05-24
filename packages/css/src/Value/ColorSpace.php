<?php

declare(strict_types=1);

namespace Phpdftk\Css\Value;

enum ColorSpace
{
    case sRGB;
    case DisplayP3;
    case A98RGB;
    case ProPhotoRGB;
    case Rec2020;
    case OKLCH;
    case Lab;
    case Lch;
}
