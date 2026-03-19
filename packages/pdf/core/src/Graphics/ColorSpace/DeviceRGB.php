<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Graphics\ColorSpace;

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
