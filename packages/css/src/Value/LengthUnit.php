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

    // Container-relative — CSS Containment 3 §6. Resolve against the
    // nearest size-query container (an element with
    // `container-type: size` / `inline-size`); fall through to 0 when
    // no such container is in scope per spec §6.3.
    case Cqw = 'cqw';
    case Cqh = 'cqh';
    case Cqi = 'cqi';
    case Cqb = 'cqb';
    case Cqmin = 'cqmin';
    case Cqmax = 'cqmax';

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

    public function isContainerRelative(): bool
    {
        return in_array($this, [
            self::Cqw, self::Cqh, self::Cqi, self::Cqb, self::Cqmin, self::Cqmax,
        ], true);
    }

    public function isViewportRelative(): bool
    {
        return !$this->isAbsolute()
            && !$this->isFontRelative()
            && !$this->isContainerRelative();
    }
}
