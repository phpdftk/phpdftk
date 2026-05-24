<?php

declare(strict_types=1);

namespace Phpdftk\Css\Value;

/** `matrix(a, b, c, d, e, f)` — the 2D affine matrix form. */
final readonly class MatrixTransform extends TransformFunction
{
    public function __construct(
        public float $a,
        public float $b,
        public float $c,
        public float $d,
        public float $e,
        public float $f,
    ) {}

    public function toCss(): string
    {
        return sprintf(
            'matrix(%s, %s, %s, %s, %s, %s)',
            self::trim($this->a),
            self::trim($this->b),
            self::trim($this->c),
            self::trim($this->d),
            self::trim($this->e),
            self::trim($this->f),
        );
    }

    private static function trim(float $v): string
    {
        return fmod($v, 1.0) === 0.0 ? (string) (int) $v : (string) $v;
    }
}
