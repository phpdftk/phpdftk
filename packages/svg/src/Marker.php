<?php

declare(strict_types=1);

namespace Phpdftk\Svg;

/**
 * SVG 2 §11.6 — `<marker>` element. Defines a reusable graphic
 * (typically an arrowhead) that gets placed at vertices of
 * `<line>`, `<polyline>`, `<polygon>`, and `<path>` shapes when
 * the shape sets `marker-start` / `marker-mid` / `marker-end`.
 *
 *   <defs>
 *     <marker id="arrow" viewBox="0 0 10 10"
 *             refX="5" refY="5"
 *             markerWidth="6" markerHeight="6"
 *             orient="auto">
 *       <path d="M 0 0 L 10 5 L 0 10 z" fill="black"/>
 *     </marker>
 *   </defs>
 *
 * The marker itself never paints at the document level — it's
 * only emitted when a shape's `marker-*` property references
 * it. The Translator's painter consumes the typed accessors
 * for placement and orientation at each anchor point.
 */
final class Marker extends Element
{
    public function __construct()
    {
        parent::__construct('marker');
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

    public function refX(): float
    {
        // Per SVG 2 §11.6.3 the `refX` keyword forms `left | center |
        // right` map to viewport-relative positions. We honour them
        // numerically as 0 / 0.5*width / width when a viewBox exists.
        $raw = $this->getAttribute('refX');
        if ($raw === null || $raw === '') {
            return 0.0;
        }
        $vb = $this->viewBox();
        return match (strtolower($raw)) {
            'left' => $vb !== null ? $vb[0] : 0.0,
            'center' => $vb !== null ? $vb[0] + $vb[2] / 2.0 : 0.0,
            'right' => $vb !== null ? $vb[0] + $vb[2] : 0.0,
            default => (float) $raw,
        };
    }

    public function refY(): float
    {
        $raw = $this->getAttribute('refY');
        if ($raw === null || $raw === '') {
            return 0.0;
        }
        $vb = $this->viewBox();
        return match (strtolower($raw)) {
            'top' => $vb !== null ? $vb[1] : 0.0,
            'center' => $vb !== null ? $vb[1] + $vb[3] / 2.0 : 0.0,
            'bottom' => $vb !== null ? $vb[1] + $vb[3] : 0.0,
            default => (float) $raw,
        };
    }

    public function markerWidth(): float
    {
        return (float) ($this->getAttribute('markerWidth') ?? 3.0);
    }

    public function markerHeight(): float
    {
        return (float) ($this->getAttribute('markerHeight') ?? 3.0);
    }

    /**
     * `auto | auto-start-reverse | <angle>`. Returns:
     *   - The string `'auto'` or `'auto-start-reverse'` literally.
     *   - A float angle in degrees for the numeric form.
     *   - 0.0 when the attribute is absent.
     */
    public function orient(): string|float
    {
        $raw = strtolower($this->getAttribute('orient') ?? '');
        if ($raw === '' || $raw === '0') {
            return 0.0;
        }
        if ($raw === 'auto' || $raw === 'auto-start-reverse') {
            return $raw;
        }
        // Strip optional `deg` / `rad` / `turn` / `grad` unit.
        if (preg_match('/^([\-+]?[0-9.]+)\s*(deg|rad|turn|grad)?$/', $raw, $m) === 1) {
            $value = (float) $m[1];
            return match ($m[2] ?? 'deg') {
                'rad' => $value * 180.0 / M_PI,
                'turn' => $value * 360.0,
                'grad' => $value * 0.9,
                default => $value,
            };
        }
        return 0.0;
    }

    /**
     * `strokeWidth` (default) scales the marker by the shape's
     * current stroke width; `userSpaceOnUse` uses the user
     * coordinate system directly.
     */
    public function markerUnits(): string
    {
        $u = strtolower($this->getAttribute('markerUnits') ?? 'strokewidth');
        return $u === 'userspaceonuse' ? 'userSpaceOnUse' : 'strokeWidth';
    }
}
