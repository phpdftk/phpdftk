<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf\Layout;

use Phpdftk\FontParser\OpenTypeData;

/**
 * One face inside a CSS font family — a single `OpenTypeData` with its
 * `font-weight` / `font-style` / `font-stretch` metadata so
 * {@see FontResolver} can run CSS Fonts 4 §6 font-matching (weight matching
 * + style matching + stretch matching) over a multi-face family.
 *
 * Weight values follow the CSS 1–1000 keyword/integer system (400 = normal,
 * 700 = bold). Style is the keyword string (`normal`, `italic`, or `oblique`)
 * — normalised to lower-case at construction. Stretch is the percentage
 * along the spec's [50%, 200%] axis (50 = ultra-condensed, 100 = normal,
 * 200 = ultra-expanded).
 */
final readonly class FontFace
{
    public OpenTypeData $data;
    public int $weight;
    public string $style;
    public float $stretch;

    public function __construct(
        OpenTypeData $data,
        int $weight = 400,
        string $style = 'normal',
        float $stretch = 100.0,
    ) {
        if ($weight < 1 || $weight > 1000) {
            throw new \InvalidArgumentException(sprintf(
                'FontFace weight must be 1-1000 per CSS Fonts 4 §3.2; got %d',
                $weight,
            ));
        }
        $lcStyle = strtolower($style);
        if (!in_array($lcStyle, ['normal', 'italic', 'oblique'], true)) {
            throw new \InvalidArgumentException(sprintf(
                'FontFace style must be normal|italic|oblique per CSS Fonts 4 §3.3; got "%s"',
                $style,
            ));
        }
        if ($stretch < 50.0 || $stretch > 200.0) {
            throw new \InvalidArgumentException(sprintf(
                'FontFace stretch must be 50-200 percent per CSS Fonts 4 §3.4; got %.2f',
                $stretch,
            ));
        }
        $this->data = $data;
        $this->weight = $weight;
        $this->style = $lcStyle;
        $this->stretch = $stretch;
    }
}
