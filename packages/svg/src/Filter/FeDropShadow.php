<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Filter;

/**
 * SVG 2 Filter Effects §15.18 — `<feDropShadow>`. A composite
 * primitive equivalent to the canonical "feOffset + feGaussian-
 * Blur + feFlood + feComposite + feMerge" drop-shadow chain.
 *
 *   <feDropShadow dx="2" dy="2" stdDeviation="3"
 *                 flood-color="black" flood-opacity="0.5"/>
 */
final class FeDropShadow extends FilterPrimitive
{
    public function __construct()
    {
        parent::__construct('feDropShadow');
    }

    public function dx(): float
    {
        return (float) ($this->getAttribute('dx') ?? 2);
    }

    public function dy(): float
    {
        return (float) ($this->getAttribute('dy') ?? 2);
    }

    /**
     * @return array{0: float, 1: float}  [sigmaX, sigmaY]
     */
    public function stdDeviation(): array
    {
        $raw = trim($this->getAttribute('stdDeviation') ?? '');
        if ($raw === '') {
            return [2.0, 2.0];
        }
        $parts = preg_split('/[\s,]+/', $raw) ?: [];
        $sx = max(0.0, (float) ($parts[0] ?? 0));
        $sy = isset($parts[1]) ? max(0.0, (float) $parts[1]) : $sx;
        return [$sx, $sy];
    }

    public function floodColor(): string
    {
        return $this->getAttribute('flood-color') ?? 'black';
    }

    public function floodOpacity(): float
    {
        $v = $this->getAttribute('flood-opacity');
        if ($v === null) {
            return 1.0;
        }
        return max(0.0, min(1.0, (float) $v));
    }
}
