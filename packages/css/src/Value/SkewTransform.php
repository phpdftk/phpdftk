<?php

declare(strict_types=1);

namespace Phpdftk\Css\Value;

final readonly class SkewTransform extends TransformFunction
{
    public function __construct(public float $xDeg, public float $yDeg = 0.0) {}

    public function toCss(): string
    {
        if ($this->yDeg === 0.0) {
            return sprintf('skew(%sdeg)', self::trim($this->xDeg));
        }
        return sprintf('skew(%sdeg, %sdeg)', self::trim($this->xDeg), self::trim($this->yDeg));
    }

    private static function trim(float $v): string
    {
        return fmod($v, 1.0) === 0.0 ? (string) (int) $v : (string) $v;
    }
}
