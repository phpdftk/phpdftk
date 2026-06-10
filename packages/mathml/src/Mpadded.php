<?php

declare(strict_types=1);

namespace Phpdftk\Mathml;

/**
 * `<mpadded>` — wrap a child and adjust its bounding box (MathML
 * Core §3.3.6).
 *
 * Attributes (each is a CSS `<length>` or one of the relative forms
 * `+expr` / `-expr` / `expr` that override the child's intrinsic
 * value):
 *
 *   - `width`   — total horizontal advance after padding.
 *   - `height`  — ascent above baseline. (Painter ignores for v1.)
 *   - `depth`   — descent below baseline. (Painter ignores for v1.)
 *   - `lspace`  — pad on the LEFT before the content (shifts content
 *     right; cursor advances accordingly).
 *   - `voffset` — vertical shift of the content (positive raises,
 *     negative lowers). Painter applies this around the children's
 *     paint so subsequent siblings flow on the original baseline.
 *
 * The relative forms (e.g. `width="+1em"`) modify the intrinsic
 * value. The v1 painter honours `width` and `lspace` as absolute
 * em values; relative-form parsing rounds back to absolute via the
 * estimated child width.
 *
 * `<mpadded>` is transparent for content — children render in source
 * order with the padding adjustments applied.
 */
final class Mpadded extends Element
{
    public function __construct()
    {
        parent::__construct('mpadded');
    }

    /**
     * Absolute `width` in em (caller multiplies by fontSize). Returns
     * null for absent / unparseable / relative-form values so the
     * painter falls back to the natural content width.
     */
    public function widthEm(): ?float
    {
        return $this->parseAbsoluteLengthEm($this->attributes['width'] ?? null);
    }

    /**
     * Absolute `height` in em (ascent above baseline). Returns null
     * for absent / unparseable / relative-form values so the painter
     * falls back to the natural content ascent.
     */
    public function heightEm(): ?float
    {
        return $this->parseAbsoluteLengthEm($this->attributes['height'] ?? null);
    }

    /**
     * Absolute `depth` in em (descent below baseline). Returns null
     * for absent / unparseable / relative-form values so the painter
     * falls back to the natural content descent.
     */
    public function depthEm(): ?float
    {
        return $this->parseAbsoluteLengthEm($this->attributes['depth'] ?? null);
    }

    /**
     * Absolute `lspace` in em. Negative shifts the content left.
     * Returns null when absent so the painter defaults to 0.
     */
    public function lspaceEm(): ?float
    {
        return $this->parseAbsoluteLengthEm($this->attributes['lspace'] ?? null);
    }

    /**
     * Absolute `voffset` in em. Positive values raise the content
     * above its natural baseline; negative values lower it. The
     * painter applies the offset around the children's paint and
     * restores the cursor to the original baseline afterward so
     * subsequent siblings flow correctly.
     */
    public function voffsetEm(): ?float
    {
        return $this->parseAbsoluteLengthEm($this->attributes['voffset'] ?? null);
    }

    /**
     * Resolve `widthEm()` × fontSize, but route px / pt through a
     * direct 1:1 conversion instead of em scaling. Mirrors
     * html-to-pdf's CSS cascade: 1 CSS px == 1 PDF pt regardless
     * of font-size, em scales with font-size. Returns null when
     * the attribute is absent or unparseable.
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

    public function lspacePt(float $fontSize): ?float
    {
        return $this->parseLengthPt($this->attributes['lspace'] ?? null, $fontSize);
    }

    public function voffsetPt(float $fontSize): ?float
    {
        return $this->parseLengthPt($this->attributes['voffset'] ?? null, $fontSize);
    }

    /**
     * Parse a CSS `<length>` directly to pt. px / pt convert 1:1
     * (matching html-to-pdf); em / ex / unitless scale with
     * fontSize.
     */
    private function parseLengthPt(?string $raw, float $fontSize): ?float
    {
        if ($raw === null) {
            return null;
        }
        $trimmed = trim($raw);
        if ($trimmed === '') {
            return null;
        }
        if (!preg_match('/^([+-]?\d*\.?\d+)\s*([a-zA-Z%]*)$/', $trimmed, $m)) {
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

    /**
     * Parse a CSS `<length>` to em. Treats leading `+`/`-` as plain
     * signs (the relative-vs-absolute distinction surfaces only when
     * the painter compares with the child's intrinsic value, which
     * v1 doesn't track).
     */
    private function parseAbsoluteLengthEm(?string $raw): ?float
    {
        if ($raw === null) {
            return null;
        }
        $trimmed = trim($raw);
        if ($trimmed === '') {
            return null;
        }
        if (!preg_match('/^([+-]?\d*\.?\d+)\s*([a-zA-Z%]*)$/', $trimmed, $m)) {
            return null;
        }
        $value = (float) $m[1];
        $unit = strtolower($m[2]);
        // Legacy em-only accessor. Uses the v1 default 12pt font
        // size for px / pt conversions so the result matches
        // html-to-pdf's 1 CSS px == 1 PDF pt convention. Painter
        // call sites now prefer widthPt / heightPt / depthPt /
        // lspacePt / voffsetPt which take fontSize and handle the
        // unit cases without this drift.
        return match ($unit) {
            'em', ''   => $value,
            'ex'       => $value * 0.5,
            'px', 'pt' => $value / 12.0,
            default    => null,
        };
    }
}
