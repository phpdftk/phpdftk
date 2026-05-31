<?php

declare(strict_types=1);

namespace Phpdftk\Css\Value;

/**
 * CSS Color 5 §6 — `device-cmyk(<c> <m> <y> <k> [/ <alpha>]? [, <rgb-fallback>]?)`.
 *
 * Direct CMYK colour specification, preserved verbatim so the
 * PDF writer can emit it through PDF's native /DeviceCMYK
 * colour space without round-tripping through sRGB. Components
 * are stored as 0..1 floats (the spec accepts both 0..1
 * numbers and percentages — both forms are normalised here).
 *
 *   color: device-cmyk(0 1 1 0);                full red
 *   color: device-cmyk(20% 0% 100% 10%);        olive-yellow
 *   color: device-cmyk(0 1 1 0 / 50%);          half-transparent red
 *   color: device-cmyk(0 1 1 0, #ff0000);       with sRGB fallback
 *
 * The optional sRGB fallback is what screen contexts use; the
 * CMYK components are preserved for print PDF output where they
 * become a /DeviceCMYK colorspace operator.
 */
final readonly class DeviceCmyk extends Value
{
    public function __construct(
        public float $c,
        public float $m,
        public float $y,
        public float $k,
        public float $alpha = 1.0,
        public ?Color $fallback = null,
    ) {}

    public function toCss(): string
    {
        $body = sprintf('%g %g %g %g', $this->c, $this->m, $this->y, $this->k);
        if ($this->alpha < 1.0) {
            $body .= ' / ' . sprintf('%g', $this->alpha);
        }
        if ($this->fallback !== null) {
            return 'device-cmyk(' . $body . ', ' . $this->fallback->toCss() . ')';
        }
        return 'device-cmyk(' . $body . ')';
    }
}
