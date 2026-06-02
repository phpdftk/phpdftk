<?php

declare(strict_types=1);

namespace Phpdftk\Svg;

/**
 * SVG 2 §11.6 — `<foreignObject>` embeds non-SVG content
 * (HTML, MathML, etc.) inside an SVG fragment. The element
 * carries a target rectangle (`x`, `y`, `width`, `height`)
 * for laying out the foreign content, but the content itself
 * is in another XML namespace and isn't part of the SVG
 * painting tree.
 *
 * For the static print renderer we recognise the element so
 * it parses to a typed class, but its descendants are not
 * walked by the SVG dispatch — rendering nested HTML/MathML
 * needs a separate pipeline that this typed class lets the
 * caller wire up explicitly.
 */
final class ForeignObject extends Element
{
    public function __construct()
    {
        parent::__construct('foreignObject');
    }

    public function x(): float
    {
        return (float) ($this->getAttribute('x') ?? 0);
    }

    public function y(): float
    {
        return (float) ($this->getAttribute('y') ?? 0);
    }

    public function width(): ?float
    {
        $w = $this->getAttribute('width');
        return $w !== null ? (float) $w : null;
    }

    public function height(): ?float
    {
        $h = $this->getAttribute('height');
        return $h !== null ? (float) $h : null;
    }
}
