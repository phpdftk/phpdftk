<?php declare(strict_types=1);
namespace Phpdftk\Color;

/**
 * Lossless color model conversions between RGB, CMYK, and Gray.
 *
 * RGB↔CMYK uses the standard subtractive model. RGB→Gray uses
 * ITU-R BT.601 luma coefficients (0.299R + 0.587G + 0.114B) to
 * match perceived brightness.
 */
final class ColorConverter {
    public static function rgbToCmyk(RgbColor $rgb): CmykColor {
        $r = $rgb->r;
        $g = $rgb->g;
        $b = $rgb->b;
        $k = 1.0 - max($r, $g, $b);
        if ($k >= 1.0) {
            return new CmykColor(0.0, 0.0, 0.0, 1.0);
        }
        $c = (1.0 - $r - $k) / (1.0 - $k);
        $m = (1.0 - $g - $k) / (1.0 - $k);
        $y = (1.0 - $b - $k) / (1.0 - $k);
        return new CmykColor(
            max(0.0, min(1.0, $c)),
            max(0.0, min(1.0, $m)),
            max(0.0, min(1.0, $y)),
            max(0.0, min(1.0, $k)),
        );
    }

    public static function cmykToRgb(CmykColor $cmyk): RgbColor {
        $c = $cmyk->c;
        $m = $cmyk->m;
        $y = $cmyk->y;
        $k = $cmyk->k;
        return new RgbColor(
            max(0.0, min(1.0, (1.0 - $c) * (1.0 - $k))),
            max(0.0, min(1.0, (1.0 - $m) * (1.0 - $k))),
            max(0.0, min(1.0, (1.0 - $y) * (1.0 - $k))),
        );
    }

    public static function rgbToGray(RgbColor $rgb): GrayColor {
        $gray = 0.299 * $rgb->r + 0.587 * $rgb->g + 0.114 * $rgb->b;
        return new GrayColor(max(0.0, min(1.0, $gray)));
    }

    public static function grayToRgb(GrayColor $gray): RgbColor {
        return new RgbColor($gray->gray, $gray->gray, $gray->gray);
    }
}
