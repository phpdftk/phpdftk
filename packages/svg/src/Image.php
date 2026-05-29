<?php

declare(strict_types=1);

namespace Phpdftk\Svg;

/**
 * SVG `<image>` per SVG 2 §12.6 — references a raster (or SVG) bitmap to
 * be drawn at a given position and size.
 *
 * `href()` deliberately returns the **raw** URL string. The parser never
 * opens the URL; the painter in `phpdftk/svg-to-pdf` resolves it through
 * the resource-loader gate established for `<img>` in 1L. That gate
 * decides what's safe — `data:` URLs, allowlisted local files, etc. —
 * keeping the security policy in one place.
 */
final class Image extends Element
{
    public function __construct()
    {
        parent::__construct('image');
    }

    public function x(): float
    {
        return $this->parseLengthOrZero('x');
    }

    public function y(): float
    {
        return $this->parseLengthOrZero('y');
    }

    /**
     * `width` — non-negative. SVG 2 §12.6 makes both width and height
     * optional; we return null when absent so the painter knows to
     * fall back to the source bitmap's intrinsic dimensions.
     */
    public function width(): ?float
    {
        return $this->parseOptionalNonNegativeLength('width');
    }

    public function height(): ?float
    {
        return $this->parseOptionalNonNegativeLength('height');
    }

    /**
     * Raw URL string from `href` (preferred) or `xlink:href` (legacy).
     * Unlike `<use>` we don't strip anything — the painter / resource
     * loader needs the full string to decide whether the URL points at
     * a `data:` blob, an allowlisted local file, or something to reject.
     */
    public function href(): ?string
    {
        $raw = $this->getAttribute('href')
            ?? $this->getAttribute('xlink:href');
        if ($raw === null) {
            return null;
        }
        $trimmed = trim($raw);
        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * `preserveAspectRatio` returned as a raw string. The grammar
     * (SVG 2 §7.10) is `<align> [ <meetOrSlice> ]?` — parsing into a
     * typed value is a 3O painter concern.
     */
    public function preserveAspectRatio(): ?string
    {
        $raw = $this->getAttribute('preserveAspectRatio');
        return $raw === null ? null : trim($raw);
    }

    private function parseOptionalNonNegativeLength(string $attr): ?float
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
