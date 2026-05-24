<?php

declare(strict_types=1);

namespace Phpdftk\Css\Value;

/**
 * CSS length units per CSS Values 4. Categorised into absolute (resolvable
 * to the pixel without context), font-relative (need a font size to
 * resolve), and viewport-relative (need a viewport size).
 */
enum LengthUnit: string
{
    // Absolute
    case Px = 'px';
    case Pt = 'pt';
    case Pc = 'pc';
    case Cm = 'cm';
    case Mm = 'mm';
    case Q = 'q';
    case In = 'in';

    // Font-relative
    case Em = 'em';
    case Rem = 'rem';
    case Ex = 'ex';
    case Ch = 'ch';
    case Lh = 'lh';
    case Rlh = 'rlh';

    // Viewport-relative
    case Vw = 'vw';
    case Vh = 'vh';
    case Vmin = 'vmin';
    case Vmax = 'vmax';
    case Vi = 'vi';
    case Vb = 'vb';
    case Svw = 'svw';
    case Svh = 'svh';
    case Lvw = 'lvw';
    case Lvh = 'lvh';
    case Dvw = 'dvw';
    case Dvh = 'dvh';

    public function isAbsolute(): bool
    {
        return in_array($this, [
            self::Px, self::Pt, self::Pc, self::Cm, self::Mm, self::Q, self::In,
        ], true);
    }

    public function isFontRelative(): bool
    {
        return in_array($this, [
            self::Em, self::Rem, self::Ex, self::Ch, self::Lh, self::Rlh,
        ], true);
    }

    public function isViewportRelative(): bool
    {
        return !$this->isAbsolute() && !$this->isFontRelative();
    }
}
