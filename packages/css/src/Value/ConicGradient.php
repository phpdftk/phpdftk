<?php

declare(strict_types=1);

namespace Phpdftk\Css\Value;

/**
 * `conic-gradient([from <angle>]? [at <position>]?, <stops>)`
 * per CSS Backgrounds 4 + CSS Images 4 §3.5.
 *
 * A conic gradient sweeps a colour around a centre point. The
 * `from` angle controls the starting orientation (default 0deg —
 * the 12 o'clock direction). The `at` position controls the
 * centre (default 50% 50% — the centre of the box). Stops are
 * angular per the spec; this storage tags them with the same
 * `GradientStop` type linear / radial use, with the `position`
 * field carrying the angle as a percentage of the full sweep
 * (0 to 1 maps to 0deg to 360deg).
 */
final readonly class ConicGradient extends Gradient
{
    /**
     * @param float $fromAngleDeg Starting orientation, normalised
     *                            to [0, 360).
     * @param float|null $centerX `at <position>` x coordinate
     *                            (percentage 0-1). Null = default
     *                            (centre, 0.5).
     * @param float|null $centerY `at <position>` y coordinate
     *                            (percentage 0-1). Null = default
     *                            (centre, 0.5).
     * @param list<GradientStop> $stops
     */
    public function __construct(
        public float $fromAngleDeg,
        public ?float $centerX,
        public ?float $centerY,
        public array $stops,
        public bool $repeating = false,
    ) {}

    public function toCss(): string
    {
        $prefix = $this->repeating ? 'repeating-conic-gradient' : 'conic-gradient';
        $head = [];
        if ($this->fromAngleDeg !== 0.0) {
            $angle = fmod($this->fromAngleDeg, 1.0) === 0.0
                ? (string) (int) $this->fromAngleDeg
                : (string) $this->fromAngleDeg;
            $head[] = 'from ' . $angle . 'deg';
        }
        if ($this->centerX !== null || $this->centerY !== null) {
            $cx = ($this->centerX ?? 0.5) * 100;
            $cy = ($this->centerY ?? 0.5) * 100;
            $head[] = 'at ' . (fmod($cx, 1.0) === 0.0 ? (int) $cx : $cx) . '% '
                . (fmod($cy, 1.0) === 0.0 ? (int) $cy : $cy) . '%';
        }
        $stops = implode(', ', array_map(static fn(GradientStop $s): string => $s->toCss(), $this->stops));
        return $head === []
            ? sprintf('%s(%s)', $prefix, $stops)
            : sprintf('%s(%s, %s)', $prefix, implode(' ', $head), $stops);
    }
}
