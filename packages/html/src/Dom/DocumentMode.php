<?php

declare(strict_types=1);

namespace Phpdftk\Html\Dom;

/**
 * Document mode resolved by the parser from the DOCTYPE per WHATWG §13.2.6.2.
 */
enum DocumentMode
{
    case NoQuirks;
    case LimitedQuirks;
    case Quirks;
}
