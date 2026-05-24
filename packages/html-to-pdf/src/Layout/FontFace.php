<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf\Layout;

use Phpdftk\FontParser\OpenTypeData;

/**
 * One face inside a CSS font family — a single `OpenTypeData` with its
 * `font-weight` / `font-style` metadata so {@see FontResolver} can run
 * CSS Fonts 4 §6 font-matching (weight matching + style matching) over a
 * multi-face family.
 *
 * Weight values follow the CSS 1–1000 keyword/integer system (400 = normal,
 * 700 = bold). Style is the keyword string (`normal`, `italic`, or `oblique`)
 * — normalised to lower-case at construction.
 *
 * The face has no "stretch" / "size" axis at Phase 1; those land alongside
 * variable-font axis handling later.
 */
final readonly class FontFace
{
    public OpenTypeData $data;
    public int $weight;
    public string $style;

    public function __construct(
        OpenTypeData $data,
        int $weight = 400,
        string $style = 'normal',
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
        $this->data = $data;
        $this->weight = $weight;
        $this->style = $lcStyle;
    }
}
