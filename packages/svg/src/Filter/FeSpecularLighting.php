<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Filter;

/**
 * SVG 2 Filter Effects §15.15 — `<feSpecularLighting>`. Same
 * surface-from-alpha model as feDiffuseLighting, but with the
 * Phong specular reflection model.
 *
 *   lighting-color / surfaceScale / kernelUnitLength
 *     — same as feDiffuseLighting.
 *   specularConstant:  Phong coefficient ks, default 1
 *   specularExponent:  Phong shininess exponent, default 1
 */
final class FeSpecularLighting extends FilterPrimitive
{
    public function __construct()
    {
        parent::__construct('feSpecularLighting');
    }

    public function lightingColor(): string
    {
        return $this->getAttribute('lighting-color') ?? 'white';
    }

    public function surfaceScale(): float
    {
        return (float) ($this->getAttribute('surfaceScale') ?? 1);
    }

    public function specularConstant(): float
    {
        return max(0.0, (float) ($this->getAttribute('specularConstant') ?? 1));
    }

    public function specularExponent(): float
    {
        return max(1.0, (float) ($this->getAttribute('specularExponent') ?? 1));
    }

    /**
     * @return array{0: float, 1: float}|null
     */
    public function kernelUnitLength(): ?array
    {
        $raw = trim($this->getAttribute('kernelUnitLength') ?? '');
        if ($raw === '') {
            return null;
        }
        $parts = preg_split('/[\s,]+/', $raw) ?: [];
        $dx = max(0.0, (float) ($parts[0] ?? 1));
        $dy = isset($parts[1]) ? max(0.0, (float) $parts[1]) : $dx;
        return [$dx, $dy];
    }
}
