<?php

declare(strict_types=1);

namespace Phpdftk\Svg;

/**
 * Shared base for SVG elements that establish a viewport — currently
 * `<svg>` (root) and `<symbol>`. Both carry a `viewBox` and optional
 * `width` / `height`, and both apply the viewBox-to-viewport mapping
 * described in SVG 2 §7. The two raw width/height accessors stay
 * `string`-typed because the spec permits unit suffixes (`%`, `em`, …)
 * that downstream code may want to round-trip.
 */
abstract class ViewportElement extends Element
{
    /**
     * Parsed `viewBox` per SVG 2 §7.7 — `[minX, minY, width, height]`,
     * or null if no viewBox is set. Negative width/height return null
     * (the spec invalidates them).
     *
     * @return array{0: float, 1: float, 2: float, 3: float}|null
     */
    public function viewBox(): ?array
    {
        $raw = $this->getAttribute('viewBox');
        if ($raw === null) {
            return null;
        }
        $parts = preg_split('/[\s,]+/', trim($raw)) ?: [];
        if (count($parts) !== 4) {
            return null;
        }
        foreach ($parts as $p) {
            if (!is_numeric($p)) {
                return null;
            }
        }
        $w = (float) $parts[2];
        $h = (float) $parts[3];
        if ($w < 0 || $h < 0) {
            return null;
        }
        return [(float) $parts[0], (float) $parts[1], $w, $h];
    }

    /** Raw `width=""` attribute string (may include a unit), or null. */
    public function widthAttribute(): ?string
    {
        return $this->getAttribute('width');
    }

    /** Raw `height=""` attribute string (may include a unit), or null. */
    public function heightAttribute(): ?string
    {
        return $this->getAttribute('height');
    }
}
