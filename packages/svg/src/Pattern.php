<?php

declare(strict_types=1);

namespace Phpdftk\Svg;

use Phpdftk\Svg\Value\Transform;

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
        return $this->tileLength('x');
    }

    public function y(): float
    {
        return $this->tileLength('y');
    }

    public function width(): float
    {
        return $this->tileLength('width');
    }

    public function height(): float
    {
        return $this->tileLength('height');
    }

    /**
     * One of the tile-rectangle lengths, in the units
     * {@see patternUnits} implies.
     *
     * SVG 2 §13.3 — in the default `objectBoundingBox` mode a
     * percentage is a FRACTION of the bounding box, so `100%` is one
     * bounding box; a straight `(float)` cast read it as a hundred of
     * them and blew the tile up until a single copy covered the whole
     * shape. In `userSpaceOnUse` mode an absolute unit suffix converts
     * to CSS px through the shared table.
     *
     * An absent or unparseable value is 0, which the painter already
     * treats as "no tile, don't paint".
     */
    private function tileLength(string $attribute): float
    {
        $raw = $this->getAttribute($attribute);
        if ($raw === null) {
            return 0.0;
        }
        if (preg_match(
            '/^\s*([+-]?(?:\d+\.?\d*|\.\d+)(?:[eE][+-]?\d+)?)\s*(%|[a-zA-Z]*)/',
            $raw,
            $m,
        ) !== 1) {
            return 0.0;
        }
        $value = (float) $m[1];
        if ($m[2] === '%') {
            return $value / 100.0;
        }
        return $value * Element::absoluteUnitScale($m[2]);
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
    /**
     * SVG 2 §13.3 — `patternTransform`, an extra transform applied to
     * the pattern tile's coordinate system on top of `patternUnits`.
     *
     * Read through the same attribute-or-CSS path as `transform`, so a
     * `<style>` rule reaches it too. Null when absent, empty, `none`,
     * or malformed.
     */
    public function patternTransform(): ?Transform
    {
        $raw = $this->presentationOrStyle('patternTransform')
            ?? $this->getAttribute('patternTransform');
        if ($raw === null || trim($raw) === '' || strtolower(trim($raw)) === 'none') {
            return null;
        }
        try {
            return Transform::parse($raw);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    public function href(): ?string
    {
        return $this->getAttribute('href')
            ?? $this->getAttribute('xlink:href');
    }
}
