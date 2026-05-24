<?php

declare(strict_types=1);

namespace Phpdftk\Css\Value;

final readonly class ScaleTransform extends TransformFunction
{
    public function __construct(
        public float $sx,
        public float $sy,
        public ?float $sz = null,
    ) {}

    public function toCss(): string
    {
        if ($this->sz !== null) {
            return sprintf('scale3d(%s, %s, %s)', self::trim($this->sx), self::trim($this->sy), self::trim($this->sz));
        }
        if ($this->sx === $this->sy) {
            return sprintf('scale(%s)', self::trim($this->sx));
        }
        return sprintf('scale(%s, %s)', self::trim($this->sx), self::trim($this->sy));
    }

    private static function trim(float $v): string
    {
        return fmod($v, 1.0) === 0.0 ? (string) (int) $v : (string) $v;
    }
}
