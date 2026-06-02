<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Filter;

/**
 * SVG 2 Filter Effects §15.4 — `<feBlend mode in in2>`. Mixes
 * two inputs using a Porter-Duff / blend-mode pixel-pair function.
 *
 * `mode` matches the CSS Compositing 1 §3 blend-mode keywords:
 *
 *   normal | multiply | screen | overlay | darken | lighten |
 *   color-dodge | color-burn | hard-light | soft-light |
 *   difference | exclusion | hue | saturation | color | luminosity
 */
final class FeBlend extends FilterPrimitive
{
    public function __construct()
    {
        parent::__construct('feBlend');
    }

    public function mode(): string
    {
        $v = strtolower($this->getAttribute('mode') ?? 'normal');
        $known = [
            'normal', 'multiply', 'screen', 'overlay', 'darken', 'lighten',
            'color-dodge', 'color-burn', 'hard-light', 'soft-light',
            'difference', 'exclusion', 'hue', 'saturation', 'color', 'luminosity',
        ];
        return in_array($v, $known, true) ? $v : 'normal';
    }

    public function in2(): ?string
    {
        return $this->getAttribute('in2');
    }
}
