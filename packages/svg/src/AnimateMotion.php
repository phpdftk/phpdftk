<?php

declare(strict_types=1);

namespace Phpdftk\Svg;

/**
 * SVG 2 §19.6 — `<animateMotion>` moves an element along a path.
 * `path` carries the SVG-path data; `rotate` controls whether the
 * element rotates to follow the tangent.
 */
final class AnimateMotion extends Animation
{
    public function __construct()
    {
        parent::__construct('animateMotion');
    }

    public function path(): ?string
    {
        return $this->getAttribute('path');
    }

    public function rotate(): ?string
    {
        return $this->getAttribute('rotate');
    }

    public function keyPoints(): ?string
    {
        return $this->getAttribute('keyPoints');
    }
}
