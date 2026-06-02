<?php

declare(strict_types=1);

namespace Phpdftk\Svg;

/**
 * SVG 2 §19.6 — `<mpath>` child of `<animateMotion>` that
 * references a path by `href` (or legacy `xlink:href`) instead
 * of carrying inline path data via the `path` attribute on the
 * parent.
 */
final class MPath extends Element
{
    public function __construct()
    {
        parent::__construct('mpath');
    }

    public function href(): ?string
    {
        return $this->getAttribute('href')
            ?? $this->getAttribute('xlink:href');
    }
}
