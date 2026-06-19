<?php

declare(strict_types=1);

namespace Phpdftk\Css\Value;

/**
 * Convert a {@see Color} from any of CSS Color 4 / 5's storage spaces into
 * sRGB so the painter can emit the components through PDF's DeviceRGB
 * operators. All math is single-precision-float per CSS Color 4 §17, with
 * standard published matrices.
 *
 * Conversion chains, by source space:
 *  - sRGB                         — already sRGB, no-op.
 *  - HWB                          — straight CSS Color 4 §6 formula.
 *  - Lab / LCH                    — Lab ↔ XYZ-D50 (Lab is D50-referenced),
 *                                   chromatic adaptation D50 → D65 via
 *                                   Bradford, then linear-sRGB → sRGB.
 *  - OKLab / OKLCH                — straight matrix to linear-sRGB
 *                                   (CSS Color 4 §10), no whitepoint chain.
 *  - sRGB-linear                  — gamma encode.
 *  - XYZ / XYZD65                 — XYZ-D65 → linear-sRGB → sRGB.
 *  - XYZD50                       — D50 → D65 → linear-sRGB → sRGB.
 *  - DisplayP3 / Rec2020 / A98RGB / ProPhotoRGB
 *                                 — space-specific degamma → linear-space
 *                                   → space-specific matrix → XYZ → sRGB.
 *
 * Components outside [0, 1] after the chain are clipped — proper gamut
 * mapping (CSS Color 4 §13) is a follow-up.
 */
final class ColorConverter
{
    private const M_XYZD65_TO_LINEAR_SRGB = [
        [ 3.2409699419045226, -1.5373831775700935, -0.4986107602930034],
        [-0.9692436362808796,  1.8759675015077202,  0.0415550574071756],
        [ 0.0556300796969936, -0.2039769588889765,  1.0569715142428784],
    ];

    private const M_D50_TO_D65_BRADFORD = [
        [ 0.9554734527042182, -0.0230985368742614,  0.0632593086610217],
        [-0.0283697069632081,  1.0099954580058226,  0.0210413821411275],
        [ 0.0123140016883199, -0.0205076964334779,  1.3303659366080753],
    ];

    private const M_LINEAR_DISPLAYP3_TO_XYZD65 = [
        [0.4865709486482162, 0.2656676931690927, 0.1982172852343625],
        [0.2289745640697488, 0.6917385218365064, 0.0792869140937449],
        [0.0000000000000000, 0.0451133818589026, 1.0439443689009760],
    ];

    private const M_LINEAR_A98RGB_TO_XYZD65 = [
        [0.5766690429101305, 0.1855582379065463, 0.1882286462349947],
        [0.2973449340834686, 0.6273635662554663, 0.0752914996610652],
        [0.0270313613864123, 0.0706888525358272, 0.9913375368376388],
    ];

    private const M_LINEAR_REC2020_TO_XYZD65 = [
        [0.6369580483012911, 0.1446169035862080, 0.1688809751641320],
        [0.2627002120112671, 0.6779980715188708, 0.0593017164698621],
        [0.0000000000000000, 0.0280726930490874, 1.0609850577107909],
    ];

    private const M_LINEAR_PROPHOTORGB_TO_XYZD50 = [
        [0.7977666449006423, 0.1351812974005331, 0.0313477341283742],
        [0.2880748288194013, 0.7118352342418857, 0.0000899369387123],
        [0.0000000000000000, 0.0000000000000000, 0.8251046025104602],
    ];

    private const M_OKLAB_LMS_TO_LINEAR_SRGB = [
        [ 4.0767416621, -3.3077115913,  0.2309699292],
        [-1.2684380046,  2.6097574011, -0.3413193965],
        [-0.0041960863, -0.7034186147,  1.7076147010],
    ];

    private const M_OKLAB_LAB_TO_LMS_CBRT = [
        [1.0, 0.3963377774, 0.2158037573],
        [1.0, -0.1055613458, -0.0638541728],
        [1.0, -0.0894841775, -1.2914855480],
    ];

    /** Convert any Color to an sRGB-space Color in `[0, 1]`. */
    public static function toSrgb(Color $color): Color
    {
        return match ($color->space) {
            ColorSpace::sRGB => $color,
            ColorSpace::sRGBLinear => self::fromLinearSrgb($color->r, $color->g, $color->b, $color->a),
            ColorSpace::HWB => self::fromHwb($color->r, $color->g, $color->b, $color->a),
            ColorSpace::Lab => self::labToSrgb($color->r, $color->g, $color->b, $color->a),
            ColorSpace::Lch => self::lchToSrgbGamutMapped($color->r, $color->g, $color->b, $color->a),
            ColorSpace::OKLab => self::fromOklab($color->r, $color->g, $color->b, $color->a),
            ColorSpace::OKLCH => self::oklchToSrgbGamutMapped($color->r, $color->g, $color->b, $color->a),
            ColorSpace::XYZ, ColorSpace::XYZD65
                => self::fromXyzD65($color->r, $color->g, $color->b, $color->a),
            ColorSpace::XYZD50 => self::fromXyzD50($color->r, $color->g, $color->b, $color->a),
            ColorSpace::DisplayP3 => self::fromWideRgb($color->r, $color->g, $color->b, $color->a, self::M_LINEAR_DISPLAYP3_TO_XYZD65, isD50: false),
            ColorSpace::DisplayP3Linear => self::fromWideRgb($color->r, $color->g, $color->b, $color->a, self::M_LINEAR_DISPLAYP3_TO_XYZD65, isD50: false, skipDegamma: true),
            ColorSpace::A98RGB => self::fromWideRgb($color->r, $color->g, $color->b, $color->a, self::M_LINEAR_A98RGB_TO_XYZD65, isD50: false),
            ColorSpace::A98RGBLinear => self::fromWideRgb($color->r, $color->g, $color->b, $color->a, self::M_LINEAR_A98RGB_TO_XYZD65, isD50: false, skipDegamma: true),
            ColorSpace::Rec2020 => self::fromWideRgb($color->r, $color->g, $color->b, $color->a, self::M_LINEAR_REC2020_TO_XYZD65, isD50: false),
            ColorSpace::Rec2020Linear => self::fromWideRgb($color->r, $color->g, $color->b, $color->a, self::M_LINEAR_REC2020_TO_XYZD65, isD50: false, skipDegamma: true),
            ColorSpace::ProPhotoRGB => self::fromWideRgb($color->r, $color->g, $color->b, $color->a, self::M_LINEAR_PROPHOTORGB_TO_XYZD50, isD50: true),
            ColorSpace::ProPhotoRGBLinear => self::fromWideRgb($color->r, $color->g, $color->b, $color->a, self::M_LINEAR_PROPHOTORGB_TO_XYZD50, isD50: true, skipDegamma: true),
        };
    }

    private static function labToSrgb(float $l, float $a, float $b, float $alpha): Color
    {
        [$x, $y, $z] = self::labToXyzD50($l, $a, $b);
        return self::fromXyzD50($x, $y, $z, $alpha);
    }

    /**
     * CSS Color 4 §13 gamut-mapping for LCH: binary-search chroma down to
     * the largest value that produces an in-sRGB result. The previous
     * implementation relied on `fromLinearSrgb`'s per-channel `clip01` to
     * fit out-of-gamut samples into sRGB, but clipping produces colours
     * that don't preserve lightness or hue — `lch(0% 110 60)` clipped to
     * `(0.295, 0, 0)` (a dark red) instead of black. Pre-search short-
     * circuits at the lightness extremes since L=0/L=100 collapse to
     * pure black/white regardless of chroma.
     */
    private static function lchToSrgbGamutMapped(float $l, float $c, float $h, float $alpha): Color
    {
        if ($l <= 0.0) {
            return new Color(0.0, 0.0, 0.0, $alpha);
        }
        if ($l >= 100.0) {
            return new Color(1.0, 1.0, 1.0, $alpha);
        }
        $buildLinear = static function (float $chroma) use ($l, $h): array {
            [$ll, $a, $b] = self::lchToLab($l, $chroma, $h);
            [$x, $y, $z] = self::labToXyzD50($ll, $a, $b);
            [$xn, $yn, $zn] = self::mul3(self::M_D50_TO_D65_BRADFORD, [$x, $y, $z]);
            return self::mul3(self::M_XYZD65_TO_LINEAR_SRGB, [$xn, $yn, $zn]);
        };
        return self::gamutMapByChroma($buildLinear, $c, $alpha);
    }

    private static function oklchToSrgbGamutMapped(float $l, float $c, float $h, float $alpha): Color
    {
        // OKLCH lightness is 0–1, not 0–100.
        if ($l <= 0.0) {
            return new Color(0.0, 0.0, 0.0, $alpha);
        }
        if ($l >= 1.0) {
            return new Color(1.0, 1.0, 1.0, $alpha);
        }
        $buildLinear = static function (float $chroma) use ($l, $h): array {
            [$lab_l, $a, $b] = self::lchToLab($l, $chroma, $h);
            [$l1, $m1, $s1] = self::mul3(self::M_OKLAB_LAB_TO_LMS_CBRT, [$lab_l, $a, $b]);
            return self::mul3(self::M_OKLAB_LMS_TO_LINEAR_SRGB, [$l1 ** 3, $m1 ** 3, $s1 ** 3]);
        };
        return self::gamutMapByChroma($buildLinear, $c, $alpha);
    }

    /**
     * Binary-search chroma in [0, maxChroma] for the largest value whose
     * converted sRGB sample is in-gamut (no channel clamped by
     * `fromLinearSrgb`). `$build(chroma)` returns the converted Color;
     * the helper compares its output to a parallel uncoloured probe
     * (`fromLinearSrgb` is the only path that clamps, so re-running with
     * a known channel value isn't needed — the clipped result equals the
     * unclipped only when the linear samples already sit in `[0, 1]`).
     */
    /**
     * Binary-search chroma in [0, maxChroma] for the largest value
     * whose linear-sRGB sample is in `[0, 1]` per channel (i.e.
     * in-gamut). `$buildLinear(chroma)` returns the UNCLIPPED
     * linear sRGB triple from the relevant color-space conversion.
     * The final sample is gamma-encoded and clipped via the regular
     * `fromLinearSrgb` path before returning.
     *
     * @param \Closure(float): array{0:float,1:float,2:float} $buildLinear
     */
    private static function gamutMapByChroma(\Closure $buildLinear, float $maxChroma, float $alpha): Color
    {
        $TOL = 1e-4;
        $fitsInGamut = static function (array $linear) use ($TOL): bool {
            [$r, $g, $b] = $linear;
            return $r >= -$TOL && $r <= 1.0 + $TOL
                && $g >= -$TOL && $g <= 1.0 + $TOL
                && $b >= -$TOL && $b <= 1.0 + $TOL;
        };
        $best = $buildLinear($maxChroma);
        if ($fitsInGamut($best)) {
            return self::fromLinearSrgb($best[0], $best[1], $best[2], $alpha);
        }
        $lo = 0.0;
        $hi = $maxChroma;
        for ($i = 0; $i < 25; $i++) {
            $mid = ($lo + $hi) / 2.0;
            $candidate = $buildLinear($mid);
            if ($fitsInGamut($candidate)) {
                $best = $candidate;
                $lo = $mid;
            } else {
                $hi = $mid;
            }
        }
        return self::fromLinearSrgb($best[0], $best[1], $best[2], $alpha);
    }

    /**
     * CSS Color 4 §6 — HWB(h, w, b) = mix(hue(h), white, w) ⊕ mix(…, black, b),
     * where hue(h) is a pure-saturation sRGB colour at the given hue.
     * Components stored as `r=h(deg)`, `g=w(0–1)`, `b=b(0–1)`.
     */
    private static function fromHwb(float $h, float $w, float $b, float $alpha): Color
    {
        if ($w + $b >= 1.0) {
            $gray = $w / ($w + $b);
            return new Color($gray, $gray, $gray, $alpha);
        }
        [$rh, $gh, $bh] = self::hueToSrgb($h);
        $r = $rh * (1.0 - $w - $b) + $w;
        $g = $gh * (1.0 - $w - $b) + $w;
        $b2 = $bh * (1.0 - $w - $b) + $w;
        return new Color(self::clip01($r), self::clip01($g), self::clip01($b2), $alpha);
    }

    /**
     * @return array{float, float, float}
     */
    private static function hueToSrgb(float $h): array
    {
        $h = fmod($h, 360.0);
        if ($h < 0.0) {
            $h += 360.0;
        }
        // sRGB hue at saturation=1, lightness=0.5 → standard hue-wheel formula.
        return self::hslToSrgb($h, 1.0, 0.5);
    }

    /**
     * @return array{float, float, float}
     */
    private static function hslToSrgb(float $h, float $s, float $l): array
    {
        $h = fmod($h, 360.0);
        if ($h < 0.0) {
            $h += 360.0;
        }
        $c = (1.0 - abs(2.0 * $l - 1.0)) * $s;
        $hp = $h / 60.0;
        $x = $c * (1.0 - abs(fmod($hp, 2.0) - 1.0));
        $m = $l - $c / 2.0;
        [$r, $g, $b] = match (true) {
            $hp < 1.0 => [$c, $x, 0.0],
            $hp < 2.0 => [$x, $c, 0.0],
            $hp < 3.0 => [0.0, $c, $x],
            $hp < 4.0 => [0.0, $x, $c],
            $hp < 5.0 => [$x, 0.0, $c],
            default => [$c, 0.0, $x],
        };
        return [$r + $m, $g + $m, $b + $m];
    }

    /**
     * Lab → XYZ-D50 per CSS Color 4 §10.4. `l` is 0–100, `a` and `b` are
     * unbounded (typical range ±125). The reference white D50 is (0.96422,
     * 1.0, 0.82521).
     *
     * @return array{float, float, float}
     */
    private static function labToXyzD50(float $l, float $a, float $b): array
    {
        $epsilon = 216.0 / 24389.0;
        $kappa = 24389.0 / 27.0;
        $fy = ($l + 16.0) / 116.0;
        $fx = $a / 500.0 + $fy;
        $fz = $fy - $b / 200.0;
        $x3 = $fx ** 3;
        $y3 = $fy ** 3;
        $z3 = $fz ** 3;
        $xN = $x3 > $epsilon ? $x3 : (116.0 * $fx - 16.0) / $kappa;
        $yN = $l > $kappa * $epsilon ? $y3 : $l / $kappa;
        $zN = $z3 > $epsilon ? $z3 : (116.0 * $fz - 16.0) / $kappa;
        return [$xN * 0.96422, $yN * 1.0, $zN * 0.82521];
    }

    /**
     * LCH → Lab (in radians-from-degrees). Works for both Lab/LCH and
     * OKLab/OKLCH because the polar transform is space-agnostic.
     *
     * @return array{float, float, float}
     */
    private static function lchToLab(float $l, float $c, float $h): array
    {
        $rad = $h * M_PI / 180.0;
        return [$l, $c * cos($rad), $c * sin($rad)];
    }

    private static function fromOklab(float $l, float $a, float $b, float $alpha): Color
    {
        // OKLab → LMS_cbrt → LMS (cube) → linear-sRGB via published matrices
        // (CSS Color 4 §10).
        [$l1, $m1, $s1] = self::mul3(self::M_OKLAB_LAB_TO_LMS_CBRT, [$l, $a, $b]);
        $l3 = $l1 ** 3;
        $m3 = $m1 ** 3;
        $s3 = $s1 ** 3;
        [$lr, $lg, $lb] = self::mul3(self::M_OKLAB_LMS_TO_LINEAR_SRGB, [$l3, $m3, $s3]);
        return self::fromLinearSrgb($lr, $lg, $lb, $alpha);
    }

    private static function fromXyzD65(float $x, float $y, float $z, float $alpha): Color
    {
        [$lr, $lg, $lb] = self::mul3(self::M_XYZD65_TO_LINEAR_SRGB, [$x, $y, $z]);
        return self::fromLinearSrgb($lr, $lg, $lb, $alpha);
    }

    private static function fromXyzD50(float $x, float $y, float $z, float $alpha): Color
    {
        [$x65, $y65, $z65] = self::mul3(self::M_D50_TO_D65_BRADFORD, [$x, $y, $z]);
        return self::fromXyzD65($x65, $y65, $z65, $alpha);
    }

    /**
     * Convert a wide-gamut RGB Color (Display-P3 / A98RGB / Rec2020 / ProPhotoRGB).
     * Each space has its own gamma; we use sRGB-style gamma for sRGB-like
     * spaces and 1.8 for ProPhoto (per CSS Color 4 §10.7). `$matrix` maps
     * the LINEAR components to XYZ.
     *
     * @param array<int, array<int, float>> $matrix
     */
    private static function fromWideRgb(
        float $r,
        float $g,
        float $b,
        float $alpha,
        array $matrix,
        bool $isD50,
        bool $skipDegamma = false,
    ): Color {
        // CSS Color 4 §10 — the `*-linear` variants of wide-gamut spaces
        // share the encoded-variant's matrix but skip its transfer curve
        // (the input components are already linear-light).
        if ($skipDegamma) {
            $lr = $r;
            $lg = $g;
            $lb = $b;
        } elseif ($matrix === self::M_LINEAR_PROPHOTORGB_TO_XYZD50) {
            // ProPhoto RGB — CSS Color 4 §10.6 transfer is a simple
            // 1.8 power curve in both directions; the sub-threshold
            // linear segment is handled by the same formula in CSS
            // (no piecewise split).
            $lr = $r >= 0.0 ? $r ** 1.8 : -((-$r) ** 1.8);
            $lg = $g >= 0.0 ? $g ** 1.8 : -((-$g) ** 1.8);
            $lb = $b >= 0.0 ? $b ** 1.8 : -((-$b) ** 1.8);
        } elseif ($matrix === self::M_LINEAR_A98RGB_TO_XYZD65) {
            // Adobe RGB (1998) — CSS Color 4 §10.4: gamma is 563/256
            // = 2.19921875 (the spec rounds to "approximately 2.2"
            // but mandates the exact rational). No piecewise split.
            // The previous shared-with-sRGB transfer pulled the
            // converted samples 6-7% off green/red (WPT a98rgb-001,
            // predefined-007/-008).
            $lr = $r >= 0.0 ? $r ** 2.19921875 : -((-$r) ** 2.19921875);
            $lg = $g >= 0.0 ? $g ** 2.19921875 : -((-$g) ** 2.19921875);
            $lb = $b >= 0.0 ? $b ** 2.19921875 : -((-$b) ** 2.19921875);
        } elseif ($matrix === self::M_LINEAR_REC2020_TO_XYZD65) {
            // BT.2020 — CSS Color 4 §10.5 piecewise transfer:
            //   c < β   → c / 4.5
            //   c ≥ β   → ((|c| + α - 1) / α) ^ (1 / 0.45),  α = 1.09929682680944, β = 0.018053968510807
            // (Cross-rate signs through.)
            $lr = self::rec2020Degamma($r);
            $lg = self::rec2020Degamma($g);
            $lb = self::rec2020Degamma($b);
        } else {
            $lr = self::srgbDegamma($r);
            $lg = self::srgbDegamma($g);
            $lb = self::srgbDegamma($b);
        }
        [$x, $y, $z] = self::mul3($matrix, [$lr, $lg, $lb]);
        if ($isD50) {
            [$x, $y, $z] = self::mul3(self::M_D50_TO_D65_BRADFORD, [$x, $y, $z]);
        }
        return self::fromXyzD65($x, $y, $z, $alpha);
    }

    private static function fromLinearSrgb(float $r, float $g, float $b, float $alpha): Color
    {
        return new Color(
            self::clip01(self::srgbGamma($r)),
            self::clip01(self::srgbGamma($g)),
            self::clip01(self::srgbGamma($b)),
            $alpha,
        );
    }

    private static function srgbGamma(float $v): float
    {
        $sign = $v < 0.0 ? -1.0 : 1.0;
        $a = abs($v);
        if ($a <= 0.0031308) {
            return $sign * 12.92 * $a;
        }
        return $sign * (1.055 * ($a ** (1.0 / 2.4)) - 0.055);
    }

    private static function srgbDegamma(float $v): float
    {
        $sign = $v < 0.0 ? -1.0 : 1.0;
        $a = abs($v);
        if ($a <= 0.04045) {
            return $sign * $a / 12.92;
        }
        return $sign * ((($a + 0.055) / 1.055) ** 2.4);
    }

    private static function rec2020Degamma(float $v): float
    {
        // BT.2020 transfer (CSS Color 4 §10.5): α = 1.09929682680944,
        // β linear cutoff 4.5β = 0.081242858298635 (so β ≈ 0.018053).
        $sign = $v < 0.0 ? -1.0 : 1.0;
        $a = abs($v);
        if ($a < 0.08124285829863) {
            return $sign * $a / 4.5;
        }
        return $sign * ((($a + 0.09929682680944) / 1.09929682680944) ** (1.0 / 0.45));
    }

    private static function clip01(float $v): float
    {
        if ($v < 0.0) return 0.0;
        if ($v > 1.0) return 1.0;
        return $v;
    }

    /**
     * @param array<int, array<int, float>> $m
     * @param array{0:float,1:float,2:float} $v
     * @return array{float, float, float}
     */
    private static function mul3(array $m, array $v): array
    {
        return [
            $m[0][0] * $v[0] + $m[0][1] * $v[1] + $m[0][2] * $v[2],
            $m[1][0] * $v[0] + $m[1][1] * $v[1] + $m[1][2] * $v[2],
            $m[2][0] * $v[0] + $m[2][1] * $v[1] + $m[2][2] * $v[2],
        ];
    }
}
