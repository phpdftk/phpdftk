<?php

declare(strict_types=1);

namespace Phpdftk\ImageMetadata;

/**
 * Parsed image header metadata — everything needed to build a PDF ImageXObject
 * without decoding pixel data.
 */
final class ImageInfo
{
    public function __construct(
        public readonly int    $width,
        public readonly int    $height,
        public readonly string $colorSpace,      // 'DeviceGray', 'DeviceRGB', 'DeviceCMYK'
        public readonly int    $bitsPerComponent,
        public readonly string $format,          // 'jpeg', 'png', 'gif', 'tiff', 'webp', 'svg'
        public readonly bool   $hasAlpha = false,
        public readonly ?int   $xDpi = null,
        public readonly ?int   $yDpi = null,
        public readonly ?string $iccProfile = null,
        // Intrinsic aspect ratio (width / height) for resolution-
        // independent images — populated by `SvgParser` when the
        // root `<svg>` carries either explicit width/height or a
        // `viewBox`. Raster parsers leave this null; the ratio is
        // already implicit in their pixel `width`/`height`.
        public readonly ?float $intrinsicRatio = null,
    ) {}
}
