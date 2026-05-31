<?php

declare(strict_types=1);

namespace Phpdftk\Css\Value;

/**
 * `linear-gradient(<angle> | to <side>, <stops>)`. The angle is stored in
 * degrees normalised to [0, 360). When the input used `to <side>` (e.g.
 * `to right`) the parser converts it to the equivalent angle.
 */
final readonly class LinearGradient extends Gradient
{
    /** @param list<GradientStop> $stops */
    public function __construct(
        public float $angleDeg,
        public array $stops,
        public bool $repeating = false,
        public ?ColorSpace $interpolationSpace = null,
        public ?HueInterpolation $hueInterpolation = null,
    ) {}

    public function toCss(): string
    {
        $prefix = $this->repeating ? 'repeating-linear-gradient' : 'linear-gradient';
        $stops = implode(', ', array_map(static fn(GradientStop $s): string => $s->toCss(), $this->stops));
        $angle = (fmod($this->angleDeg, 1.0) === 0.0 ? (int) $this->angleDeg : $this->angleDeg) . 'deg';
        $head = $angle . InterpolationMethodCss::serialise($this->interpolationSpace, $this->hueInterpolation);
        return sprintf('%s(%s, %s)', $prefix, $head, $stops);
    }
}
