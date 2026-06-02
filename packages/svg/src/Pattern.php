<?php

declare(strict_types=1);

namespace Phpdftk\Svg;

/**
 * SVG 2 §13.3 — `<pattern>` element. Defines a tile that paints
 * into the fill or stroke of any shape that references it via
 * `fill="url(#patternId)"`. Pattern attributes mirror `<marker>`
 * and `<use>`:
 *
 *   - x, y, width, height — the tile rectangle in user space
 *     (or pattern units, depending on patternUnits).
 *   - patternUnits — `objectBoundingBox` (default, %-relative
 *     to the referencing shape's bbox) or `userSpaceOnUse`.
 *   - patternContentUnits — same flag for child positioning,
 *     defaults to `userSpaceOnUse`.
 *   - viewBox + preserveAspectRatio for content scaling.
 *   - patternTransform — additional transform on the tile.
 *
 * For the static print renderer the typed class lifts these
 * attributes off the generic Element surface; PDF Tiling Pattern
 * (Pattern Type 1) emission is the next deliverable.
 */
final class Pattern extends Element
{
    public function __construct()
    {
        parent::__construct('pattern');
    }

    public function x(): float
    {
        return (float) ($this->getAttribute('x') ?? 0);
    }

    public function y(): float
    {
        return (float) ($this->getAttribute('y') ?? 0);
    }

    public function width(): float
    {
        return (float) ($this->getAttribute('width') ?? 0);
    }

    public function height(): float
    {
        return (float) ($this->getAttribute('height') ?? 0);
    }

    public function patternUnits(): string
    {
        $u = strtolower($this->getAttribute('patternUnits') ?? 'objectboundingbox');
        return $u === 'userspaceonuse' ? 'userSpaceOnUse' : 'objectBoundingBox';
    }

    public function patternContentUnits(): string
    {
        $u = strtolower($this->getAttribute('patternContentUnits') ?? 'userspaceonuse');
        return $u === 'objectboundingbox' ? 'objectBoundingBox' : 'userSpaceOnUse';
    }

    /**
     * @return array{0: float, 1: float, 2: float, 3: float}|null
     */
    public function viewBox(): ?array
    {
        $vb = $this->getAttribute('viewBox');
        if ($vb === null) {
            return null;
        }
        $parts = preg_split('/[\s,]+/', trim($vb)) ?: [];
        if (count($parts) !== 4) {
            return null;
        }
        return [
            (float) $parts[0],
            (float) $parts[1],
            (float) $parts[2],
            (float) $parts[3],
        ];
    }

    /**
     * `href` (or legacy `xlink:href`) — when present, the
     * referenced pattern's attributes inherit into this one.
     * See SVG 2 §13.3 chain-resolution rules.
     */
    public function href(): ?string
    {
        return $this->getAttribute('href')
            ?? $this->getAttribute('xlink:href');
    }
}
