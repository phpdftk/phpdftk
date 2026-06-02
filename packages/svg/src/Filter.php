<?php

declare(strict_types=1);

namespace Phpdftk\Svg;

/**
 * SVG 2 Filter Effects §6.1 — `<filter>` element. Defines a
 * filter graph (list of primitive `<fe*>` operations) applied
 * to any shape that references it via `filter="url(#filterId)"`
 * or the CSS `filter` property.
 *
 *   <defs>
 *     <filter id="blur">
 *       <feGaussianBlur stdDeviation="2"/>
 *     </filter>
 *   </defs>
 *   <rect filter="url(#blur)" width="100" height="100"/>
 *
 * The element itself never paints — like marker / pattern /
 * gradient, it's a referenceable definition. PDF SoftMask-based
 * filter rendering (CSS Filter Effects 1 §4) needs the raster
 * pipeline and is a future deliverable.
 */
final class Filter extends Element
{
    public function __construct()
    {
        parent::__construct('filter');
    }

    public function x(): float
    {
        return (float) ($this->getAttribute('x') ?? -0.1);
    }

    public function y(): float
    {
        return (float) ($this->getAttribute('y') ?? -0.1);
    }

    public function width(): float
    {
        return (float) ($this->getAttribute('width') ?? 1.2);
    }

    public function height(): float
    {
        return (float) ($this->getAttribute('height') ?? 1.2);
    }

    public function filterUnits(): string
    {
        $u = strtolower($this->getAttribute('filterUnits') ?? 'objectboundingbox');
        return $u === 'userspaceonuse' ? 'userSpaceOnUse' : 'objectBoundingBox';
    }

    public function primitiveUnits(): string
    {
        $u = strtolower($this->getAttribute('primitiveUnits') ?? 'userspaceonuse');
        return $u === 'objectboundingbox' ? 'objectBoundingBox' : 'userSpaceOnUse';
    }

    public function href(): ?string
    {
        return $this->getAttribute('href')
            ?? $this->getAttribute('xlink:href');
    }
}
