<?php

declare(strict_types=1);

namespace Phpdftk\Svg;

/**
 * SVG 2 §6.3 — `<view>` element. Defines a named viewport
 * declaratively so external links can target a specific region
 * of an SVG document via `file.svg#viewId`. Properties mirror
 * the root `<svg>`: `viewBox`, `preserveAspectRatio`, optionally
 * `zoomAndPan` and `viewTarget`.
 *
 * Server-side rendering only activates a view when the caller
 * passes the fragment identifier explicitly. At document level
 * the element never paints — it's a referenceable definition.
 */
final class View extends Element
{
    public function __construct()
    {
        parent::__construct('view');
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

    public function preserveAspectRatio(): ?string
    {
        return $this->getAttribute('preserveAspectRatio');
    }
}
