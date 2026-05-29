<?php

declare(strict_types=1);

namespace Phpdftk\Svg;

/**
 * SVG `<symbol>` per SVG 2 §5.5 — a template element that defines a
 * graphical content fragment intended to be referenced by `<use>`. Inherits
 * `viewBox` / `widthAttribute` / `heightAttribute` from `ViewportElement`
 * since the spec maps a referenced symbol's coordinate system the same
 * way `<svg>` does.
 */
final class Symbol extends ViewportElement
{
    public function __construct()
    {
        parent::__construct('symbol');
    }
}
