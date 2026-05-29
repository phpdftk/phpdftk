<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Gradient;

use Phpdftk\Svg\Element;
use Phpdftk\Svg\SvgDocument;
use Phpdftk\Svg\Value\Transform;

/**
 * Shared base for SVG 2 §13 gradients (`<linearGradient>`,
 * `<radialGradient>`). Carries the attributes both forms share —
 * coordinate units, optional transform, spread method, and the `href`
 * chain used to inherit colour stops from another gradient.
 *
 * The stop-inheritance walk is the most subtle piece: per SVG 2 §13.4,
 * if a gradient has its own `<stop>` children they win; otherwise the
 * `href` chain is walked at access time. Cycles (`href="#a"` → `#b` →
 * `#a`) are broken via a visited set.
 */
abstract class Gradient extends Element
{
    /**
     * `gradientUnits` — coordinate-system mode for the gradient's
     * geometric attributes (`x1`/`y1`/... or `cx`/`cy`/...). Default
     * `objectBoundingBox` per SVG 2 §13.6.5.
     *
     * @return 'userSpaceOnUse'|'objectBoundingBox'
     */
    public function gradientUnits(): string
    {
        $raw = $this->getAttribute('gradientUnits');
        if ($raw === null) {
            return 'objectBoundingBox';
        }
        return match (trim($raw)) {
            'userSpaceOnUse' => 'userSpaceOnUse',
            default => 'objectBoundingBox',
        };
    }

    /**
     * `gradientTransform` — an additional transformation applied to the
     * gradient's coordinate system. Null when absent or malformed (the
     * SVG 2 "invalid → ignored" semantics that 3C established for
     * `transform`).
     */
    public function gradientTransform(): ?Transform
    {
        $raw = $this->getAttribute('gradientTransform');
        if ($raw === null || trim($raw) === '') {
            return null;
        }
        try {
            return Transform::parse($raw);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    /**
     * `spreadMethod` — how the gradient extends past its declared range.
     * Default `pad` per SVG 2 §13.6.5.
     *
     * @return 'pad'|'reflect'|'repeat'
     */
    public function spreadMethod(): string
    {
        $raw = $this->getAttribute('spreadMethod');
        if ($raw === null) {
            return 'pad';
        }
        return match (trim($raw)) {
            'reflect' => 'reflect',
            'repeat' => 'repeat',
            default => 'pad',
        };
    }

    /**
     * Referenced gradient id (without the leading `#`), or null. Reads
     * `href` per SVG 2 §13.4 then falls back to `xlink:href`. External
     * references return null — same intra-document-only posture as
     * `Use_`.
     */
    public function href(): ?string
    {
        $raw = $this->getAttribute('href')
            ?? $this->getAttribute('xlink:href');
        if ($raw === null) {
            return null;
        }
        $trimmed = trim($raw);
        if (!str_starts_with($trimmed, '#')) {
            return null;
        }
        $id = substr($trimmed, 1);
        return $id === '' ? null : $id;
    }

    /**
     * Effective colour stops, walking the `href` chain when this
     * gradient has none of its own (SVG 2 §13.4). Cycles in the chain
     * (e.g. `#a` → `#b` → `#a`) are broken by a visited set.
     *
     * @return list<Stop>
     */
    public function stops(SvgDocument $doc): array
    {
        return $this->resolveStops($doc, []);
    }

    /**
     * @param array<string, true> $visited element-id set, used to break href cycles
     * @return list<Stop>
     */
    private function resolveStops(SvgDocument $doc, array $visited): array
    {
        $own = [];
        foreach ($this->children as $child) {
            if ($child instanceof Stop) {
                $own[] = $child;
            }
        }
        if ($own !== []) {
            return $own;
        }

        $href = $this->href();
        if ($href === null || isset($visited[$href])) {
            return [];
        }
        $referent = $doc->findById($href);
        if (!$referent instanceof self) {
            return [];
        }

        $visited[$href] = true;
        return $referent->resolveStops($doc, $visited);
    }
}
