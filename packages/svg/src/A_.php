<?php

declare(strict_types=1);

namespace Phpdftk\Svg;

/**
 * SVG 2 §12.1.1 — `<a>` element. A container that wraps any
 * SVG content and turns the wrapped region into a hyperlink.
 *
 *   <a href="https://example.com">
 *     <rect x="10" y="10" width="80" height="20"/>
 *   </a>
 *
 * The `href` (or legacy `xlink:href`) attribute is the link
 * target; `target`, `download`, `rel`, `hreflang`, `type`,
 * `referrerpolicy` are surface attributes preserved on the
 * element but only `href` and `target` are honoured at print
 * time. Class is named `A_` because `A` clashes with the
 * single-letter PHPStan IGNORE convention; SVG callers see
 * `<a>` as usual.
 */
final class A_ extends Element
{
    public function __construct()
    {
        parent::__construct('a');
    }

    public function href(): ?string
    {
        return $this->getAttribute('href')
            ?? $this->getAttribute('xlink:href');
    }

    public function target(): ?string
    {
        return $this->getAttribute('target');
    }
}
