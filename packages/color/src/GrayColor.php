<?php declare(strict_types=1);
namespace Phpdftk\Color;

/**
 * Grayscale color (0.0 = black, 1.0 = white) — maps to PDF DeviceGray.
 */
final class GrayColor implements ColorInterface {
    public function __construct(
        public readonly float $gray,
    ) {
        if ($gray < 0.0 || $gray > 1.0) {
            throw new \InvalidArgumentException("Gray value must be 0.0–1.0, got $gray");
        }
    }

    public static function black(): self {
        return new self(0.0);
    }

    public static function white(): self {
        return new self(1.0);
    }

    public function toRgb(): RgbColor {
        return ColorConverter::grayToRgb($this);
    }

    /** @return array<int, float> */
    public function toArray(): array {
        return [$this->gray];
    }

    public function getColorSpace(): string {
        return 'DeviceGray';
    }
}
