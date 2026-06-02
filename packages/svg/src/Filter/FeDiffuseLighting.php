<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Filter;

/**
 * SVG 2 Filter Effects §15.15 — `<feDiffuseLighting>`. Renders
 * a height-map of the input under a Lambertian diffuse-light
 * model. Surface "height" comes from the input alpha channel.
 *
 * The light source comes from one child light-source element
 * (`<feDistantLight>`, `<fePointLight>`, or `<feSpotLight>`).
 *
 *   lighting-color:   colour of incident light (default white)
 *   surfaceScale:     height-map vertical scale, default 1
 *   diffuseConstant:  Lambertian coefficient kd, default 1
 *   kernelUnitLength: derivative kernel size, default 1
 */
final class FeDiffuseLighting extends FilterPrimitive
{
    public function __construct()
    {
        parent::__construct('feDiffuseLighting');
    }

    public function lightingColor(): string
    {
        return $this->getAttribute('lighting-color') ?? 'white';
    }

    public function surfaceScale(): float
    {
        return (float) ($this->getAttribute('surfaceScale') ?? 1);
    }

    public function diffuseConstant(): float
    {
        return max(0.0, (float) ($this->getAttribute('diffuseConstant') ?? 1));
    }

    /**
     * @return array{0: float, 1: float}|null
     *   [dx, dy] kernel sample spacing, or null when unspecified.
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
