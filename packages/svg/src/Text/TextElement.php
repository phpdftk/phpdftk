<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Text;

/**
 * SVG `<text>` per SVG 2 §11. Named `TextElement` (not `Text`) so it
 * doesn't shadow the leaf `Phpdftk\Svg\Text` data node used for `#text`
 * children. Text content is reachable via `$this->children` — `Tspan`
 * children and `Phpdftk\Svg\Text` data nodes interleave per spec.
 */
final class TextElement extends TextPositioningElement
{
    public function __construct()
    {
        parent::__construct('text');
    }
}
