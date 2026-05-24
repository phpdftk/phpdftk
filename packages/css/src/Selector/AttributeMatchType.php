<?php

declare(strict_types=1);

namespace Phpdftk\Css\Selector;

/**
 * Attribute-selector match operators per Selectors 4 §6.5.
 *
 * - Exists: `[attr]`
 * - Equals: `[attr=value]`
 * - Includes: `[attr~=value]` — space-separated list contains value
 * - DashMatch: `[attr|=value]` — equal or starts with `value-`
 * - PrefixMatch: `[attr^=value]`
 * - SuffixMatch: `[attr$=value]`
 * - SubstringMatch: `[attr*=value]`
 */
enum AttributeMatchType: string
{
    case Exists = 'exists';
    case Equals = '=';
    case Includes = '~=';
    case DashMatch = '|=';
    case PrefixMatch = '^=';
    case SuffixMatch = '$=';
    case SubstringMatch = '*=';
}
