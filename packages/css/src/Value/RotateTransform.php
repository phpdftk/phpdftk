<?php

declare(strict_types=1);

namespace Phpdftk\Css\Value;

final readonly class RotateTransform extends TransformFunction
{
    public function __construct(
        public float $angleDeg,
        public float $ax = 0.0,
        public float $ay = 0.0,
        public float $az = 1.0,
    ) {}

    public function toCss(): string
    {
        if ($this->ax !== 0.0 || $this->ay !== 0.0 || $this->az !== 1.0) {
            return sprintf(
                'rotate3d(%s, %s, %s, %sdeg)',
                self::trim($this->ax),
                self::trim($this->ay),
                self::trim($this->az),
                self::trim($this->angleDeg),
            );
        }
        return sprintf('rotate(%sdeg)', self::trim($this->angleDeg));
    }

    private static function trim(float $v): string
    {
        return fmod($v, 1.0) === 0.0 ? (string) (int) $v : (string) $v;
    }
}
