<?php

declare(strict_types=1);

namespace Phpdftk\Mathml;

/**
 * `<mfrac>` — fraction layout (MathML Core §3.3.2).
 *
 * Exactly two children expected: numerator first, denominator second.
 * Renders as a vertically stacked pair with a horizontal fraction bar
 * between them by default (`linethickness` attribute controls bar
 * thickness; `0` means no bar — used for binomial coefficients).
 *
 * `bevelled` is deprecated in MathML Core; we preserve the attribute
 * for parser round-trips but the painter ignores it.
 *
 * Painter scope: the v1 renderer stacks the children vertically using
 * PDF text-rise + line-matrix repositioning but does NOT draw the
 * fraction bar yet — that requires breaking out of the text block to
 * emit path operators, which means threading the absolute fraction
 * coordinates through the Translator. Deferred to a follow-up; the
 * binomial form (`linethickness="0"`) renders correctly today.
 */
final class Mfrac extends Element
{
    public function __construct()
    {
        parent::__construct('mfrac');
    }

    /**
     * `linethickness` per Core §3.3.2 — bar thickness in CSS pixels.
     * Defaults to `1.0` for normal fractions; `"0"` is the binomial
     * form. Returns null for absent or unrecognised values so the
     * painter falls back to the spec default.
     */
    public function linethickness(): ?float
    {
        $raw = $this->attributes['linethickness'] ?? null;
        if ($raw === null) {
            return null;
        }
        $trimmed = trim($raw);
        if ($trimmed === '') {
            return null;
        }
        if (!is_numeric($trimmed)) {
            return null;
        }
        $value = (float) $trimmed;
        return $value < 0.0 ? null : $value;
    }

    /**
     * `displaystyle` per Core §3.3.2 — when true, the fraction is
     * laid out at display-style sizes (taller numerator/denominator);
     * when false, scripted-style (smaller, denser). The v1 painter
     * doesn't yet vary glyph metrics by style; returns the parsed
     * value so a future style-aware renderer can act on it.
     */
    public function displaystyle(): ?bool
    {
        $raw = $this->attributes['displaystyle'] ?? null;
        if ($raw === null) {
            return null;
        }
        $value = strtolower(trim($raw));
        return match ($value) {
            'true' => true,
            'false' => false,
            default => null,
        };
    }
}
