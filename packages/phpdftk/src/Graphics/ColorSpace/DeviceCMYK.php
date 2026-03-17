<?php

declare(strict_types=1);

namespace Phpdftk\Graphics\ColorSpace;

/**
 * DeviceCMYK color space.
 */
class DeviceCMYK extends ColorSpace
{
    public function toPdf(): string
    {
        return '/DeviceCMYK';
    }
}
