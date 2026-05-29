<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Value\Paint;

use Phpdftk\Svg\Value\Paint;

/**
 * `none` — the explicit "do not paint" value. Distinct from an absent
 * attribute, which inherits per CSS rules (handled in 3J).
 */
final class None_ extends Paint {}
