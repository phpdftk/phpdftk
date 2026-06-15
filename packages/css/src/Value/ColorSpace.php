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
    case Lab;
    case Lch;
    case OKLab;
    case OKLCH;
    case XYZ;
    case XYZD50;
    case XYZD65;
    case sRGBLinear;
    case DisplayP3Linear;
    case Rec2020Linear;
    case A98RGBLinear;
    case ProPhotoRGBLinear;
    case HWB;
}
