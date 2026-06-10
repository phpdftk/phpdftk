<?php

declare(strict_types=1);

namespace Phpdftk\Mathml;

/**
 * `<mspace>` - empty box with explicit dimensions (MathML Core 3.2.7).
 *
 * Renders nothing - purely a layout primitive that reserves
 * horizontal (and conceptually vertical) space between siblings.
 *
 * Attributes:
 *   - width  - horizontal advance.
 *   - height - ascent above baseline. (Painter ignores for v1.)
 *   - depth  - descent below baseline. (Painter ignores for v1.)
 *
 * Each is a CSS length (e.g. 1em, 5px, 0.5ex). Unknown units default
 * to em. Negative widths shift the cursor backward.
 *
 * Per Core, mspace has no children; any present round-trip but
 * don't render.
 */
final class Mspace extends Element
{
    public function __construct()
    {
        parent::__construct('mspace');
    }

    public function widthEm(): ?float
    {
        return $this->parseLengthEm($this->attributes['width'] ?? null);
    }

    public function heightEm(): ?float
    {
        return $this->parseLengthEm($this->attributes['height'] ?? null);
    }

    public function depthEm(): ?float
    {
        return $this->parseLengthEm($this->attributes['depth'] ?? null);
    }

    /**
     * pt-resolved width, with px / pt mapped 1:1 to PDF pt and
     * em / ex scaled by fontSize. Mirrors html-to-pdf's CSS
     * cascade. Preferred over widthEm() at painter call sites
     * that need correct sizing at non-default fontSize.
     */
    public function widthPt(float $fontSize): ?float
    {
        return $this->parseLengthPt($this->attributes['width'] ?? null, $fontSize);
    }

    public function heightPt(float $fontSize): ?float
    {
        return $this->parseLengthPt($this->attributes['height'] ?? null, $fontSize);
    }

    public function depthPt(float $fontSize): ?float
    {
        return $this->parseLengthPt($this->attributes['depth'] ?? null, $fontSize);
    }

    private function parseLengthPt(?string $raw, float $fontSize): ?float
    {
        if ($raw === null) {
            return null;
        }
        $trimmed = trim($raw);
        if ($trimmed === '') {
            return null;
        }
        if (!preg_match('/^(-?\d*\.?\d+)\s*([a-zA-Z%]*)$/', $trimmed, $m)) {
            return null;
        }
        $value = (float) $m[1];
        $unit = strtolower($m[2]);
        return match ($unit) {
            'em', ''   => $value * $fontSize,
            'ex'       => $value * 0.5 * $fontSize,
            'px', 'pt' => $value,
            default    => null,
        };
    }

    private function parseLengthEm(?string $raw): ?float
    {
        if ($raw === null) {
            return null;
        }
        $trimmed = trim($raw);
        if ($trimmed === '') {
            return null;
        }
        if (!preg_match('/^(-?\d*\.?\d+)\s*([a-zA-Z%]*)$/', $trimmed, $m)) {
            return null;
        }
        $value = (float) $m[1];
        $unit = strtolower($m[2]);
        if ($unit === 'em' || $unit === '') {
            return $value;
        }
        if ($unit === 'ex') {
            return $value * 0.5;
        }
        // px / pt: assume the v1 default font size of 12 pt so the
        // result lines up with html-to-pdf's 1 CSS px == 1 PDF pt
        // convention (the css/html-to-pdf cascade treats px as the
        // canonical unit and emits it directly as PDF user-space
        // pt). At a non-default math font size the conversion
        // drifts; same trade-off as Mpadded.
        if ($unit === 'px' || $unit === 'pt') {
            return $value / 12.0;
        }
        return null;
    }
}
