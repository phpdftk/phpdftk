<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Value\Paint;

use Phpdftk\Svg\Value\Paint;

/**
 * `currentColor` — defers to the value of the `color` property at paint
 * time. The painter resolves it against the current style; the parser
 * doesn't have enough context to inline a colour value here.
 */
final class CurrentColor extends Paint {}
