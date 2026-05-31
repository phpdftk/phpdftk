<?php

declare(strict_types=1);

namespace Phpdftk\Css\Value;

/**
 * `cubic-bezier(x1, y1, x2, y2)` per CSS Easing 1 §3.4. The four
 * control points define an easing curve from (0, 0) to (1, 1) via
 * intermediate handles (x1, y1) and (x2, y2). x coordinates must
 * be in [0, 1]; y coordinates may overshoot for spring-like
 * easings.
 */
final readonly class CubicBezier extends Value
{
    public function __construct(
        public float $x1,
        public float $y1,
        public float $x2,
        public float $y2,
    ) {}

    public function toCss(): string
    {
        return sprintf(
            'cubic-bezier(%s, %s, %s, %s)',
            self::trim($this->x1),
            self::trim($this->y1),
            self::trim($this->x2),
            self::trim($this->y2),
        );
    }

    private static function trim(float $v): string
    {
        return fmod($v, 1.0) === 0.0 ? (string) (int) $v : (string) $v;
    }
}
