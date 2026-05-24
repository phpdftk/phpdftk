<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf\Layout;

/**
 * Result of {@see FontResolver::resolveMatch()}: the chosen {@see FontFace}
 * plus per-axis match flags so consumers know whether the face fully
 * satisfies the requested weight / style or only partially (e.g. the
 * resolver picked the closest 400-normal face for a `font-weight: 700`
 * request because no bold face was registered).
 *
 * The painter uses the flags to decide whether to apply the synthetic
 * fake-bold (text mode 2 stroke) and fake-italic (skewed text matrix)
 * fallbacks. A `false` flag triggers the synthetic effect; a `true` flag
 * skips it so the real face's shape isn't double-bolded / double-skewed.
 */
final readonly class FontMatch
{
    public function __construct(
        public FontFace $face,
        public bool $matchesWeight,
        public bool $matchesStyle,
    ) {}
}
