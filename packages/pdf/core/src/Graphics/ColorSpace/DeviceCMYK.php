<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Graphics\ColorSpace;

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
