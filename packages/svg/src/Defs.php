<?php

declare(strict_types=1);

namespace Phpdftk\Svg;

/**
 * SVG `<defs>` per SVG 2 §5.6 — a container for referenceable elements
 * (gradients, patterns, symbols, paths …) that should not paint themselves.
 * The parser preserves the children verbatim; the painter walks any
 * `<use>` reference that points here at paint time.
 */
final class Defs extends Element
{
    public function __construct()
    {
        parent::__construct('defs');
    }
}
