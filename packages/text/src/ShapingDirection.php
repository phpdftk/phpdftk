<?php

declare(strict_types=1);

namespace Phpdftk\Text;

/**
 * Text rendering direction within a single shaped run.
 *
 * - `Ltr` — horizontal, left-to-right.
 * - `Rtl` — horizontal, right-to-left (logical-order shaping; visual reorder
 *   is the bidi reorderer's job, not the shaper's).
 * - `Ttb` — vertical, top-to-bottom. For vertical-writing-mode layouts;
 *   uses the font's vertical advance metrics when available.
 */
enum ShapingDirection
{
    case Ltr;
    case Rtl;
    case Ttb;
}
