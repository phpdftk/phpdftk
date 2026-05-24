<?php

declare(strict_types=1);

namespace Phpdftk\Css\Value;

/**
 * `radial-gradient([<shape> <size>] [at <position>], <stops>)`. Phase 1A.2-bis
 * carries the parsed shape/sizing/position through; the renderer resolves
 * them against the box at paint time.
 */
final readonly class RadialGradient extends Gradient
{
    /**
     * @param list<GradientStop> $stops
     */
    public function __construct(
        public GradientShape $shape,
        public ?Length $sizeX,
        public ?Length $sizeY,
        public ?Length $centerX,
        public ?Length $centerY,
        public array $stops,
        public bool $repeating = false,
    ) {}

    public function toCss(): string
    {
        $prefix = $this->repeating ? 'repeating-radial-gradient' : 'radial-gradient';
        $stops = implode(', ', array_map(static fn(GradientStop $s): string => $s->toCss(), $this->stops));
        $shape = $this->shape === GradientShape::Circle ? 'circle' : 'ellipse';
        $size = '';
        if ($this->sizeX !== null) {
            $size = ' ' . $this->sizeX->toCss();
            if ($this->sizeY !== null) {
                $size .= ' ' . $this->sizeY->toCss();
            }
        }
        $position = '';
        if ($this->centerX !== null && $this->centerY !== null) {
            $position = ' at ' . $this->centerX->toCss() . ' ' . $this->centerY->toCss();
        }
        return sprintf('%s(%s%s%s, %s)', $prefix, $shape, $size, $position, $stops);
    }
}
