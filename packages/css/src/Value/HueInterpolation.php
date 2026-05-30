<?php

declare(strict_types=1);

namespace Phpdftk\Css\Value;

/**
 * CSS Color 5 §3.2 — hue interpolation method for polar color
 * spaces (hsl, hwb, lch, oklch). When mixing two colors across
 * a hue gap, four strategies select which way around the colour
 * wheel to travel.
 *
 *   Shorter     — go the shorter way (default).
 *   Longer      — go the longer way.
 *   Increasing  — always increase the hue value (passes through
 *                 0/360 if necessary).
 *   Decreasing  — always decrease.
 */
enum HueInterpolation: string
{
    case Shorter = 'shorter';
    case Longer = 'longer';
    case Increasing = 'increasing';
    case Decreasing = 'decreasing';
}
