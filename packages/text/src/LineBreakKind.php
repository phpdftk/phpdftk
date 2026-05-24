<?php

declare(strict_types=1);

namespace Phpdftk\Text;

/**
 * Line-break classification per UAX #14.
 *
 * - `Mandatory` — the break must occur (line terminators: U+000A LF,
 *   U+000D CR, U+0085 NEL, U+2028 line separator, U+2029 paragraph separator).
 * - `Allowed` — the break may occur if the line is otherwise full
 *   (whitespace boundaries, after punctuation, etc.).
 */
enum LineBreakKind
{
    case Mandatory;
    case Allowed;
}
