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
 *   - `voffset` — vertical shift of the content. (Painter ignores
 *     for v1.)
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
     * Absolute `lspace` in em. Negative shifts the content left.
     * Returns null when absent so the painter defaults to 0.
     */
    public function lspaceEm(): ?float
    {
        return $this->parseAbsoluteLengthEm($this->attributes['lspace'] ?? null);
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
        return match ($unit) {
            'em', ''   => $value,
            'ex'       => $value * 0.5,
            'px'       => $value / 16.0,
            'pt'       => $value / 12.0,
            default    => null,
        };
    }
}
