<?php

declare(strict_types=1);

namespace Phpdftk\Color;

/**
 * Common contract for PDF color operands — used by ContentStream color operators.
 *
 * `getColorSpace()` returns the PDF name (DeviceRGB, DeviceCMYK, DeviceGray)
 * and `toArray()` returns component values in the order PDF operators expect.
 */
interface ColorInterface
{
    /** @return array<int, float> */
    public function toArray(): array;
    public function getColorSpace(): string;
}
