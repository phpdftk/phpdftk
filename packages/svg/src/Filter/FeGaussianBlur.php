<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Filter;

/**
 * SVG 2 Filter Effects §15.10 — `<feGaussianBlur stdDeviation>`.
 * Blurs the input by a Gaussian kernel. `stdDeviation` is either
 * a single value (isotropic blur) or two values (separate X / Y
 * standard deviations).
 *
 *   <feGaussianBlur stdDeviation="3"/>      // sx = sy = 3
 *   <feGaussianBlur stdDeviation="3 1"/>    // sx = 3, sy = 1
 */
final class FeGaussianBlur extends FilterPrimitive
{
    public function __construct()
    {
        parent::__construct('feGaussianBlur');
    }

    /**
     * @return array{0: float, 1: float}
     *   [sigmaX, sigmaY]. Defaults to [0, 0] when the attribute is
     *   absent (no blur).
     */
    public function stdDeviation(): array
    {
        $raw = trim($this->getAttribute('stdDeviation') ?? '');
        if ($raw === '') {
            return [0.0, 0.0];
        }
        $parts = preg_split('/[\s,]+/', $raw) ?: [];
        $sx = max(0.0, (float) ($parts[0] ?? 0));
        $sy = isset($parts[1]) ? max(0.0, (float) $parts[1]) : $sx;
        return [$sx, $sy];
    }

    /**
     * Per §15.10 the default edge mode is `duplicate` (clamp);
     * other values are `wrap` (tile) and `none` (transparent
     * outside).
     */
    public function edgeMode(): string
    {
        $v = strtolower($this->getAttribute('edgeMode') ?? 'duplicate');
        return in_array($v, ['duplicate', 'wrap', 'none'], true) ? $v : 'duplicate';
    }
}
