<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf;

/**
 * Stable identifier for the kinds of {@see Warning}s the renderer emits.
 * String-backed so consumers can filter / dispatch off the code; the
 * canonical strings are part of the public contract and won't change
 * within a major version.
 */
enum WarningCode: string
{
    case UnsupportedCssProperty = 'unsupported_css_property';
    case UnsupportedCssValue = 'unsupported_css_value';
    case UnsupportedDisplayType = 'unsupported_display_type';
    case UnsupportedSelector = 'unsupported_selector';
    case MissingFont = 'missing_font';
    case MissingResource = 'missing_resource';
    case ResourceLimitExceeded = 'resource_limit_exceeded';
    case DeprecatedFeature = 'deprecated_feature';
    case PageOverflow = 'page_overflow';
}
