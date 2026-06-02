<?php

declare(strict_types=1);

namespace Phpdftk\Svg;

/**
 * SVG 2 §19.5 — `<animateTransform>` interpolates a
 * `transform=` attribute. Has an additional `type` attribute
 * choosing the transform family (translate / scale / rotate /
 * skewX / skewY).
 */
final class AnimateTransform extends Animation
{
    public function __construct()
    {
        parent::__construct('animateTransform');
    }

    public function type(): string
    {
        $v = strtolower($this->getAttribute('type') ?? 'translate');
        return in_array($v, ['translate', 'scale', 'rotate', 'skewx', 'skewy'], true)
            ? $v
            : 'translate';
    }
}
