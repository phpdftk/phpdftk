<?php

declare(strict_types=1);

namespace Phpdftk\Graphics\ColorSpace;

/**
 * DeviceRGB color space.
 */
class DeviceRGB extends ColorSpace
{
    public function toPdf(): string
    {
        return '/DeviceRGB';
    }
}
