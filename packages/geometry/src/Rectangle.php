<?php declare(strict_types=1);
namespace Phpdftk\Geometry;

final class Rectangle {
    public function __construct(
        public readonly float $x,
        public readonly float $y,
        public readonly float $width,
        public readonly float $height,
    ) {}

    /** @return array<int, float> Returns [llx, lly, urx, ury] as used in PDF boxes */
    public function toArray(): array {
        return [$this->x, $this->y, $this->x + $this->width, $this->y + $this->height];
    }

    public function contains(self $other): bool {
        return $other->x >= $this->x
            && $other->y >= $this->y
            && ($other->x + $other->width)  <= ($this->x + $this->width)
            && ($other->y + $other->height) <= ($this->y + $this->height);
    }

    public function intersect(self $other): ?self {
        $x1 = max($this->x, $other->x);
        $y1 = max($this->y, $other->y);
        $x2 = min($this->x + $this->width,  $other->x + $other->width);
        $y2 = min($this->y + $this->height, $other->y + $other->height);
        if ($x2 <= $x1 || $y2 <= $y1) return null;
        return new self($x1, $y1, $x2 - $x1, $y2 - $y1);
    }

    public function union(self $other): self {
        $x1 = min($this->x, $other->x);
        $y1 = min($this->y, $other->y);
        $x2 = max($this->x + $this->width,  $other->x + $other->width);
        $y2 = max($this->y + $this->height, $other->y + $other->height);
        return new self($x1, $y1, $x2 - $x1, $y2 - $y1);
    }

    public function scale(float $factor): self {
        return new self(
            $this->x * $factor, $this->y * $factor,
            $this->width * $factor, $this->height * $factor,
        );
    }

    public function expand(float $margin): self {
        return new self(
            $this->x - $margin, $this->y - $margin,
            $this->width + 2 * $margin, $this->height + 2 * $margin,
        );
    }
}
