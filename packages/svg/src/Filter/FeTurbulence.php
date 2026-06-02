<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Filter;

/**
 * SVG 2 Filter Effects §15.17 — `<feTurbulence>`. Procedurally
 * generates a Perlin / fractal noise tile in the primitive
 * subregion. Used for organic-looking textures, paper grain,
 * surface noise, etc.
 *
 *   baseFrequency:  base frequency, one or two values
 *   numOctaves:     positive integer, default 1
 *   seed:           PRNG seed, default 0
 *   stitchTiles:    stitch | noStitch (default)
 *   type:           fractalNoise | turbulence (default)
 */
final class FeTurbulence extends FilterPrimitive
{
    public function __construct()
    {
        parent::__construct('feTurbulence');
    }

    /**
     * @return array{0: float, 1: float}  [fx, fy]
     */
    public function baseFrequency(): array
    {
        $raw = trim($this->getAttribute('baseFrequency') ?? '');
        if ($raw === '') {
            return [0.0, 0.0];
        }
        $parts = preg_split('/[\s,]+/', $raw) ?: [];
        $fx = max(0.0, (float) ($parts[0] ?? 0));
        $fy = isset($parts[1]) ? max(0.0, (float) $parts[1]) : $fx;
        return [$fx, $fy];
    }

    public function numOctaves(): int
    {
        return max(1, (int) ($this->getAttribute('numOctaves') ?? 1));
    }

    public function seed(): float
    {
        return (float) ($this->getAttribute('seed') ?? 0);
    }

    public function stitchTiles(): string
    {
        $v = strtolower($this->getAttribute('stitchTiles') ?? 'nostitch');
        return $v === 'stitch' ? 'stitch' : 'noStitch';
    }

    public function type(): string
    {
        $v = strtolower($this->getAttribute('type') ?? 'turbulence');
        return $v === 'fractalnoise' ? 'fractalNoise' : 'turbulence';
    }
}
