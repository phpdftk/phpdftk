<?php

declare(strict_types=1);

namespace Phpdftk\Text;

/**
 * Paragraph base direction per UAX #9.
 *
 * - `Ltr` — paragraph is left-to-right regardless of content.
 * - `Rtl` — paragraph is right-to-left regardless of content.
 * - `Auto` — apply rule P2: first strong character determines base
 *   direction. If no strong characters present, defaults to LTR.
 */
enum BidiBase
{
    case Ltr;
    case Rtl;
    case Auto;
}
