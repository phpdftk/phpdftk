<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Filter;

/**
 * SVG 2 Filter Effects §15.6 — `<feComposite operator in in2>`.
 * Porter-Duff composite of two inputs.
 *
 *   operator: over (default) | in | out | atop | xor | lighter |
 *             arithmetic
 *
 * When `operator="arithmetic"` the four k coefficients drive the
 * per-pixel formula `result = k1·in·in2 + k2·in + k3·in2 + k4`.
 */
final class FeComposite extends FilterPrimitive
{
    public function __construct()
    {
        parent::__construct('feComposite');
    }

    public function operator(): string
    {
        $v = strtolower($this->getAttribute('operator') ?? 'over');
        $known = ['over', 'in', 'out', 'atop', 'xor', 'lighter', 'arithmetic'];
        return in_array($v, $known, true) ? $v : 'over';
    }

    public function in2(): ?string
    {
        return $this->getAttribute('in2');
    }

    public function k1(): float
    {
        return (float) ($this->getAttribute('k1') ?? 0);
    }

    public function k2(): float
    {
        return (float) ($this->getAttribute('k2') ?? 0);
    }

    public function k3(): float
    {
        return (float) ($this->getAttribute('k3') ?? 0);
    }

    public function k4(): float
    {
        return (float) ($this->getAttribute('k4') ?? 0);
    }
}
