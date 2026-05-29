<?php

declare(strict_types=1);

namespace Phpdftk\Svg;

/**
 * SVG `<clipPath>` per SVG 2 §14.4 — a container whose graphical children
 * define a clipping region applied to elements that reference it via
 * `clip-path`. The clip region is the *geometry* of the children; their
 * paint properties don't matter, only their shape.
 */
final class ClipPath extends Element
{
    public function __construct()
    {
        parent::__construct('clipPath');
    }

    /**
     * `clipPathUnits` — coordinate system the children's path data is
     * resolved in. Default `userSpaceOnUse` per SVG 2 §14.4.1. Unknown
     * values fall back to the default rather than failing the element.
     *
     * @return 'userSpaceOnUse'|'objectBoundingBox'
     */
    public function clipPathUnits(): string
    {
        $raw = $this->getAttribute('clipPathUnits');
        if ($raw === null) {
            return 'userSpaceOnUse';
        }
        $value = trim($raw);
        return match ($value) {
            'userSpaceOnUse', 'objectBoundingBox' => $value,
            default => 'userSpaceOnUse',
        };
    }
}
