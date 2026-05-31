<?php

declare(strict_types=1);

namespace Phpdftk\Css\Value;

/**
 * Internal helper — serialise an `in <space> [<hue-interp> hue]`
 * clause for the CSS Images 4 §3.1.2 gradient interpolation
 * method. Centralises the colorspace→string + hue-interp→string
 * mapping so all three gradient types render the clause the
 * same way.
 */
final class InterpolationMethodCss
{
    private function __construct() {}

    public static function serialise(?ColorSpace $space, ?HueInterpolation $hue): string
    {
        if ($space === null) {
            return '';
        }
        $name = match ($space) {
            ColorSpace::sRGB => 'srgb',
            ColorSpace::sRGBLinear => 'srgb-linear',
            ColorSpace::DisplayP3 => 'display-p3',
            ColorSpace::A98RGB => 'a98-rgb',
            ColorSpace::ProPhotoRGB => 'prophoto-rgb',
            ColorSpace::Rec2020 => 'rec2020',
            ColorSpace::Lab => 'lab',
            ColorSpace::Lch => 'lch',
            ColorSpace::OKLab => 'oklab',
            ColorSpace::OKLCH => 'oklch',
            ColorSpace::XYZ => 'xyz',
            ColorSpace::XYZD50 => 'xyz-d50',
            ColorSpace::XYZD65 => 'xyz-d65',
        };
        $out = ' in ' . $name;
        if ($hue !== null) {
            $out .= ' ' . $hue->value . ' hue';
        }
        return $out;
    }
}
