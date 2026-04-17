<?php declare(strict_types=1);
namespace ApprLabs\ImageMetadata;

final class ImageInfo {
    public function __construct(
        public readonly int    $width,
        public readonly int    $height,
        public readonly string $colorSpace,      // 'DeviceGray', 'DeviceRGB', 'DeviceCMYK'
        public readonly int    $bitsPerComponent,
        public readonly string $format,          // 'jpeg', 'png', 'gif', 'tiff', 'webp'
        public readonly bool   $hasAlpha = false,
        public readonly ?int   $xDpi = null,
        public readonly ?int   $yDpi = null,
        public readonly ?string $iccProfile = null,
    ) {}
}
