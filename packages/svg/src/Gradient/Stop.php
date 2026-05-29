<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Gradient;

use Phpdftk\Color\ColorInterface;
use Phpdftk\Svg\Element;
use Phpdftk\Svg\Value\Color;

/**
 * SVG `<stop>` per SVG 2 §13.2.3 — one colour stop inside a gradient.
 *
 * `offset()` is clamped to `[0, 1]` (numeric in that range or a percentage
 * in `0%`–`100%`). Out-of-range values clamp per spec.
 *
 * `stopColor()` returns the parsed colour or null. The `currentColor`
 * keyword resolves to null here — the painter checks the raw attribute
 * separately when it needs that distinction.
 */
final class Stop extends Element
{
    public function __construct()
    {
        parent::__construct('stop');
    }

    public function offset(): float
    {
        $raw = $this->getAttribute('offset');
        if ($raw === null) {
            return 0.0;
        }
        $value = trim($raw);
        if (str_ends_with($value, '%')) {
            $n = substr($value, 0, -1);
            if (!is_numeric($n)) {
                return 0.0;
            }
            return max(0.0, min(1.0, ((float) $n) / 100.0));
        }
        if (!is_numeric($value)) {
            return 0.0;
        }
        return max(0.0, min(1.0, (float) $value));
    }

    public function stopColor(): ?ColorInterface
    {
        $raw = $this->getAttribute('stop-color');
        if ($raw === null) {
            return null;
        }
        return Color::parse($raw);
    }

    public function stopOpacity(): ?float
    {
        $raw = $this->getAttribute('stop-opacity');
        if ($raw === null) {
            return null;
        }
        if (!is_numeric(trim($raw))) {
            return null;
        }
        return max(0.0, min(1.0, (float) $raw));
    }
}
