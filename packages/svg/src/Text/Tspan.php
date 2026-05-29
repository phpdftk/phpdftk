<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Text;

/**
 * SVG `<tspan>` per SVG 2 §11.5 — a styled run inside a `<text>` element.
 * Carries the same positioning attributes as `<text>` (relative or
 * absolute) and may itself nest further tspans.
 */
final class Tspan extends TextPositioningElement
{
    public function __construct()
    {
        parent::__construct('tspan');
    }
}
