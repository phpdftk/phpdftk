<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Value\Paint;

use Phpdftk\Color\ColorInterface;
use Phpdftk\Svg\Value\Paint;

/**
 * A literal colour paint — wraps any `Phpdftk\Color\ColorInterface`
 * implementation (`RgbColor`, `CmykColor`, `GrayColor`). The painter passes
 * the wrapped colour to the PDF fill/stroke operator that matches its
 * declared colour space.
 */
final class SolidColor extends Paint
{
    public function __construct(public readonly ColorInterface $color) {}
}
