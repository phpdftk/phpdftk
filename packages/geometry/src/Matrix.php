<?php declare(strict_types=1);
namespace Phpdftk\Geometry;

/**
 * Affine transformation matrix [a b c d e f].
 * Transforms a point: x' = a*x + c*y + e, y' = b*x + d*y + f
 */
final class Matrix {
    public function __construct(
        public readonly float $a = 1.0,
        public readonly float $b = 0.0,
        public readonly float $c = 0.0,
        public readonly float $d = 1.0,
        public readonly float $e = 0.0,
        public readonly float $f = 0.0,
    ) {}

    public static function identity(): self { return new self(); }

    /** @return array<int, float> */
    public function toArray(): array { return [$this->a, $this->b, $this->c, $this->d, $this->e, $this->f]; }

    public function multiply(self $m): self {
        return new self(
            $this->a * $m->a + $this->b * $m->c,
            $this->a * $m->b + $this->b * $m->d,
            $this->c * $m->a + $this->d * $m->c,
            $this->c * $m->b + $this->d * $m->d,
            $this->e * $m->a + $this->f * $m->c + $m->e,
            $this->e * $m->b + $this->f * $m->d + $m->f,
        );
    }

    public function translate(float $tx, float $ty): self {
        return $this->multiply(new self(1, 0, 0, 1, $tx, $ty));
    }

    public function scale(float $sx, float $sy): self {
        return $this->multiply(new self($sx, 0, 0, $sy, 0, 0));
    }

    public function rotate(float $degrees): self {
        $r = deg2rad($degrees);
        $cos = cos($r); $sin = sin($r);
        return $this->multiply(new self($cos, $sin, -$sin, $cos, 0, 0));
    }

    public function transformPoint(Point $p): Point {
        return new Point(
            $this->a * $p->x + $this->c * $p->y + $this->e,
            $this->b * $p->x + $this->d * $p->y + $this->f,
        );
    }
}
