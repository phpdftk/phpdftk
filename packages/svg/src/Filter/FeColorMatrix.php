<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Filter;

/**
 * SVG 2 Filter Effects §15.5 — `<feColorMatrix type values>`.
 * Applies a 5×4 colour matrix to the input. Four `type` shorthands
 * map to specific 4×5 matrices:
 *
 *   matrix         — verbatim 20-value matrix
 *   saturate       — single-value (0..1) saturation adjustment
 *   hueRotate      — single-value (degrees) hue rotation
 *   luminanceToAlpha — replaces alpha with input luminance
 */
final class FeColorMatrix extends FilterPrimitive
{
    public function __construct()
    {
        parent::__construct('feColorMatrix');
    }

    public function type(): string
    {
        $v = strtolower($this->getAttribute('type') ?? 'matrix');
        $known = ['matrix', 'saturate', 'huerotate', 'luminancetoalpha'];
        return match ($v) {
            'matrix' => 'matrix',
            'saturate' => 'saturate',
            'huerotate' => 'hueRotate',
            'luminancetoalpha' => 'luminanceToAlpha',
            default => 'matrix',
        };
    }

    /**
     * Returns the parsed values list as floats. Empty when the
     * attribute is absent (callers should apply the per-type
     * default — identity matrix, saturation 1, hue 0, etc.).
     *
     * @return list<float>
     */
    public function values(): array
    {
        $raw = trim($this->getAttribute('values') ?? '');
        if ($raw === '') {
            return [];
        }
        $parts = preg_split('/[\s,]+/', $raw) ?: [];
        $out = [];
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            $out[] = (float) $part;
        }
        return $out;
    }
}
