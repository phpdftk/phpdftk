<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Filter;

/**
 * SVG 2 Filter Effects §15.11 — `<feMorphology operator radius>`.
 * Erodes or dilates the input by the given radius. Used to thicken
 * or thin shapes (text outlines, icon strokes, etc.).
 *
 *   operator: erode | dilate (default)
 *   radius:   single value (isotropic) or two values (rx, ry)
 */
final class FeMorphology extends FilterPrimitive
{
    public function __construct()
    {
        parent::__construct('feMorphology');
    }

    public function operator(): string
    {
        $v = strtolower($this->getAttribute('operator') ?? 'erode');
        return $v === 'dilate' ? 'dilate' : 'erode';
    }

    /**
     * @return array{0: float, 1: float}  [rx, ry] — both non-negative.
     */
    public function radius(): array
    {
        $raw = trim($this->getAttribute('radius') ?? '');
        if ($raw === '') {
            return [0.0, 0.0];
        }
        $parts = preg_split('/[\s,]+/', $raw) ?: [];
        $rx = max(0.0, (float) ($parts[0] ?? 0));
        $ry = isset($parts[1]) ? max(0.0, (float) $parts[1]) : $rx;
        return [$rx, $ry];
    }
}
