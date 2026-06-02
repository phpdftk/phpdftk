<?php

declare(strict_types=1);

namespace Phpdftk\Svg;

/**
 * SVG 2 §19.4 — `<animate>` interpolates a single attribute over
 * time. Print medium ignores playback; the typed class lets the
 * Translator skip the element explicitly.
 */
final class Animate extends Animation
{
    public function __construct()
    {
        parent::__construct('animate');
    }
}
