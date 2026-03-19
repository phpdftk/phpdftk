<?php declare(strict_types=1);
namespace ApprLabs\Color;

final class CmykColor implements ColorInterface {
    public function __construct(
        public readonly float $c,
        public readonly float $m,
        public readonly float $y,
        public readonly float $k,
    ) {
        foreach (['c' => $c, 'm' => $m, 'y' => $y, 'k' => $k] as $name => $value) {
            if ($value < 0.0 || $value > 1.0) {
                throw new \InvalidArgumentException("CMYK $name value must be 0.0–1.0, got $value");
            }
        }
    }

    public function toRgb(): RgbColor {
        return ColorConverter::cmykToRgb($this);
    }

    /** @return array<int, float> */
    public function toArray(): array {
        return [$this->c, $this->m, $this->y, $this->k];
    }

    public function getColorSpace(): string {
        return 'DeviceCMYK';
    }
}
