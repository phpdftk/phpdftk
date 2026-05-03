<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Graphics\ColorSpace;

/**
 * DeviceGray color space.
 */
class DeviceGray extends ColorSpace
{
    public function toPdf(): string
    {
        return '/DeviceGray';
    }
}
