<?php

declare(strict_types=1);

namespace Phpdftk\Color;

/**
 * RGB color with components in 0.0–1.0 range — maps to PDF DeviceRGB.
 *
 * Factory methods `fromInt()` and `fromHex()` accept the more common
 * 0–255 and #RRGGBB formats and normalize to the PDF float range.
 */
final class RgbColor implements ColorInterface
{
    public function __construct(
        public readonly float $r,
        public readonly float $g,
        public readonly float $b,
    ) {
        if ($r < 0.0 || $r > 1.0) {
            throw new \InvalidArgumentException("Red value must be 0.0–1.0, got $r");
        }
        if ($g < 0.0 || $g > 1.0) {
            throw new \InvalidArgumentException("Green value must be 0.0–1.0, got $g");
        }
        if ($b < 0.0 || $b > 1.0) {
            throw new \InvalidArgumentException("Blue value must be 0.0–1.0, got $b");
        }
    }

    public static function fromInt(int $r, int $g, int $b): self
    {
        return new self($r / 255.0, $g / 255.0, $b / 255.0);
    }

    public static function fromHex(string $hex): self
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) !== 6) {
            throw new \InvalidArgumentException("Invalid hex color: $hex");
        }
        return self::fromInt(
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        );
    }

    public function toCmyk(): CmykColor
    {
        return ColorConverter::rgbToCmyk($this);
    }

    public function toGray(): GrayColor
    {
        return ColorConverter::rgbToGray($this);
    }

    /** @return array<int, float> */
    public function toArray(): array
    {
        return [$this->r, $this->g, $this->b];
    }

    public function getColorSpace(): string
    {
        return 'DeviceRGB';
    }
}
