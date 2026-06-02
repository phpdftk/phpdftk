<?php

declare(strict_types=1);

namespace Phpdftk\Svg;

/**
 * SVG 2 §19.3 — `<set>` non-interpolating value assignment. Like
 * `<animate>` but jumps straight to the `to` value at `begin`.
 * Named `SetElement` (not `Set`) since `set` clashes with PHP's
 * reserved keyword.
 */
final class SetElement extends Animation
{
    public function __construct()
    {
        parent::__construct('set');
    }
}
