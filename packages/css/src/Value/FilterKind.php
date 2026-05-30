<?php

declare(strict_types=1);

namespace Phpdftk\Css\Value;

/**
 * CSS Filter Effects 1 §5 — the standard filter primitives that
 * appear in the `filter:` value-list. SVG `<filter>` URLs land
 * under the {@see FilterKind::Url} case.
 *
 * The string value matches the CSS keyword so `FilterKind::from`
 * round-trips with author CSS.
 */
enum FilterKind: string
{
    case Blur = 'blur';
    case Brightness = 'brightness';
    case Contrast = 'contrast';
    case DropShadow = 'drop-shadow';
    case Grayscale = 'grayscale';
    case HueRotate = 'hue-rotate';
    case Invert = 'invert';
    case Opacity = 'opacity';
    case Saturate = 'saturate';
    case Sepia = 'sepia';
    case Url = 'url';
}
