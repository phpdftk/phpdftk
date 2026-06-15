<?php

declare(strict_types=1);

namespace Phpdftk\Css\Value;

/**
 * Resolved colour value. RGB components are stored as floats in [0, 1] for
 * uniform handling across colour spaces; integer 0-255 forms get divided at
 * parse time. The alpha channel is always present and defaults to 1.0.
 */
final readonly class Color extends Value
{
    public function __construct(
        public float $r,
        public float $g,
        public float $b,
        public float $a = 1.0,
        public ColorSpace $space = ColorSpace::sRGB,
    ) {}

    public function toCss(): string
    {
        // For sRGB at full alpha, emit short hex when components are integer-valued.
        if ($this->space === ColorSpace::sRGB && $this->a === 1.0) {
            $r = (int) round($this->r * 255);
            $g = (int) round($this->g * 255);
            $b = (int) round($this->b * 255);
            return sprintf('#%02x%02x%02x', $r, $g, $b);
        }
        if ($this->space === ColorSpace::sRGB) {
            return sprintf(
                'rgba(%d, %d, %d, %s)',
                (int) round($this->r * 255),
                (int) round($this->g * 255),
                (int) round($this->b * 255),
                self::trim($this->a),
            );
        }
        // Wide-gamut serialise as color(<space> r g b / a).
        return sprintf(
            'color(%s %s %s %s%s)',
            $this->serializeSpace(),
            self::trim($this->r),
            self::trim($this->g),
            self::trim($this->b),
            $this->a === 1.0 ? '' : ' / ' . self::trim($this->a),
        );
    }

    private function serializeSpace(): string
    {
        return match ($this->space) {
            ColorSpace::sRGB => 'srgb',
            ColorSpace::sRGBLinear => 'srgb-linear',
            ColorSpace::DisplayP3 => 'display-p3',
            ColorSpace::DisplayP3Linear => 'display-p3-linear',
            ColorSpace::A98RGB => 'a98-rgb',
            ColorSpace::A98RGBLinear => 'a98-rgb-linear',
            ColorSpace::ProPhotoRGB => 'prophoto-rgb',
            ColorSpace::ProPhotoRGBLinear => 'prophoto-rgb-linear',
            ColorSpace::Rec2020 => 'rec2020',
            ColorSpace::Rec2020Linear => 'rec2020-linear',
            ColorSpace::Lab => 'lab',
            ColorSpace::Lch => 'lch',
            ColorSpace::OKLab => 'oklab',
            ColorSpace::OKLCH => 'oklch',
            ColorSpace::XYZ => 'xyz',
            ColorSpace::XYZD50 => 'xyz-d50',
            ColorSpace::XYZD65 => 'xyz-d65',
            ColorSpace::HWB => 'hwb',
        };
    }

    private static function trim(float $v): string
    {
        return fmod($v, 1.0) === 0.0 ? (string) (int) $v : (string) $v;
    }
}
