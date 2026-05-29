<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Gradient;

/**
 * SVG `<linearGradient>` per SVG 2 §13.6. Endpoints `(x1, y1)` and
 * `(x2, y2)` define the gradient axis; stops are colour samples along it.
 *
 * Each endpoint accessor returns null when the attribute is absent so the
 * painter can apply the unit-mode-specific defaults (e.g., `x1=0` /
 * `x2=1` in `objectBoundingBox` mode, `0` / `100%` in `userSpaceOnUse`
 * mode per SVG 2 §13.6.5).
 */
final class LinearGradient extends Gradient
{
    public function __construct()
    {
        parent::__construct('linearGradient');
    }

    public function x1(): ?float
    {
        return $this->parseOptionalLength('x1');
    }

    public function y1(): ?float
    {
        return $this->parseOptionalLength('y1');
    }

    public function x2(): ?float
    {
        return $this->parseOptionalLength('x2');
    }

    public function y2(): ?float
    {
        return $this->parseOptionalLength('y2');
    }

    private function parseOptionalLength(string $attr): ?float
    {
        $raw = $this->getAttribute($attr);
        if ($raw === null) {
            return null;
        }
        if (preg_match('/^\s*([+-]?(?:\d+\.?\d*|\.\d+)(?:[eE][+-]?\d+)?)/', $raw, $m) !== 1) {
            return null;
        }
        return (float) $m[1];
    }
}
