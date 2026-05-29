<?php

declare(strict_types=1);

namespace Phpdftk\Svg;

/**
 * SVG `<mask>` per SVG 2 §14.5 — a luminance- or alpha-based stencil applied
 * to elements that reference it via `mask`. Has its own bounding rectangle
 * (`x`, `y`, `width`, `height`), and two independent unit attributes:
 * `maskUnits` controls how the rectangle is interpreted, `maskContentUnits`
 * controls how the children's coordinates are interpreted.
 */
final class Mask extends Element
{
    public function __construct()
    {
        parent::__construct('mask');
    }

    /**
     * `maskUnits` — interpretation of the `x`/`y`/`width`/`height` rect.
     * Default `objectBoundingBox` per SVG 2 §14.5.4.
     *
     * @return 'userSpaceOnUse'|'objectBoundingBox'
     */
    public function maskUnits(): string
    {
        return $this->parseUnitsAttribute('maskUnits', 'objectBoundingBox');
    }

    /**
     * `maskContentUnits` — interpretation of the children's coordinates.
     * Default `userSpaceOnUse` per SVG 2 §14.5.4.
     *
     * @return 'userSpaceOnUse'|'objectBoundingBox'
     */
    public function maskContentUnits(): string
    {
        return $this->parseUnitsAttribute('maskContentUnits', 'userSpaceOnUse');
    }

    /**
     * `x` — null when absent so the painter applies the SVG 2 default
     * (`-10%` of the masked element's bbox in `objectBoundingBox` mode,
     * which we can't resolve here without the bbox).
     */
    public function x(): ?float
    {
        return $this->parseOptionalLength('x');
    }

    public function y(): ?float
    {
        return $this->parseOptionalLength('y');
    }

    /**
     * `width` — null when absent so the painter applies the default
     * `120%` per SVG 2 §14.5.4. Non-negative.
     */
    public function width(): ?float
    {
        $v = $this->parseOptionalLength('width');
        return $v !== null && $v < 0.0 ? null : $v;
    }

    public function height(): ?float
    {
        $v = $this->parseOptionalLength('height');
        return $v !== null && $v < 0.0 ? null : $v;
    }

    /**
     * @return 'userSpaceOnUse'|'objectBoundingBox'
     */
    private function parseUnitsAttribute(string $attr, string $default): string
    {
        $raw = $this->getAttribute($attr);
        if ($raw === null) {
            /** @var 'userSpaceOnUse'|'objectBoundingBox' $default */
            return $default;
        }
        $value = trim($raw);
        return match ($value) {
            'userSpaceOnUse', 'objectBoundingBox' => $value,
            default => $default === 'userSpaceOnUse' ? 'userSpaceOnUse' : 'objectBoundingBox',
        };
    }

    private function parseOptionalLength(string $attr): ?float
    {
        $raw = $this->getAttribute($attr);
        if ($raw === null) {
            return null;
        }
        if (preg_match('/^\s*([+-]?(?:\d+\.?\d*|\.\d+)(?:[eE][+-]?\d+)?)/', $raw, $m) !== 1) {
            return null;
        }
        return (float) $m[1];
    }
}
