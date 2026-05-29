<?php

declare(strict_types=1);

namespace Phpdftk\Svg;

/**
 * SVG `<use>` per SVG 2 §5.6 — instantiates another element somewhere
 * else in the document. The class name carries a trailing underscore to
 * avoid the PHP `use` keyword.
 *
 * Only **intra-document** references are honoured at v1. A `href` of the
 * form `other.svg#foo` (or any non-`#`-prefixed value) returns null from
 * `href()` — the parser never opens external documents (matching the
 * security posture established in 3A: no implicit cross-document loads).
 * Cross-document `<use>` resolution lands behind the same resource-loader
 * gate that html-to-pdf uses, in Phase 2.
 */
final class Use_ extends Element
{
    public function __construct()
    {
        parent::__construct('use');
    }

    /** `x` offset for the referenced subtree (SVG 2 §5.6); default 0. */
    public function x(): float
    {
        return $this->parseLengthOrZero('x');
    }

    /** `y` offset for the referenced subtree; default 0. */
    public function y(): float
    {
        return $this->parseLengthOrZero('y');
    }

    /**
     * `width` override. Null when absent so the painter knows to use the
     * referent's intrinsic width (or `100%` for `<symbol>` referents per
     * SVG 2 §5.6.2). Non-negative.
     */
    public function width(): ?float
    {
        return $this->optionalLength('width');
    }

    /** `height` override; null when absent. Non-negative. */
    public function height(): ?float
    {
        return $this->optionalLength('height');
    }

    /**
     * The referenced element's local id (without the leading `#`), or
     * null when the attribute is absent or names something we can't
     * resolve locally. Reads `href` first per SVG 2 §5.3.1 then falls
     * back to the legacy `xlink:href` for older content.
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
            // External or empty reference — no implicit cross-document
            // load at v1.
            return null;
        }
        $id = substr($trimmed, 1);
        return $id === '' ? null : $id;
    }

    /**
     * Look up the referenced element in `$doc`. Returns null when the
     * reference is missing, external, or points to an id that doesn't
     * exist.
     */
    public function resolve(SvgDocument $doc): ?Element
    {
        $id = $this->href();
        return $id === null ? null : $doc->findById($id);
    }

    private function optionalLength(string $attr): ?float
    {
        $raw = $this->getAttribute($attr);
        if ($raw === null) {
            return null;
        }
        if (preg_match('/^\s*([+-]?(?:\d+\.?\d*|\.\d+)(?:[eE][+-]?\d+)?)/', $raw, $m) !== 1) {
            return null;
        }
        $value = (float) $m[1];
        return $value < 0.0 ? null : $value;
    }
}
