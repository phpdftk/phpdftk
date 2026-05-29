<?php

declare(strict_types=1);

namespace Phpdftk\Svg;

/**
 * SVG `<g>` element per SVG 2 §5.2 — a grouping container that lets a
 * single `transform`, opacity, or clip apply to all descendants together.
 * It has no geometry of its own; the painter just nests its children under
 * any matrix the group carries.
 */
final class Group extends Element
{
    public function __construct()
    {
        parent::__construct('g');
    }
}
