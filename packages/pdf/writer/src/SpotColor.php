<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Writer;

use Phpdftk\Pdf\Core\Graphics\ColorSpace\Separation;

/**
 * Handle for a registered spot color. Bundles the colorant name with
 * the {@see Separation} value object so {@see Writer\Page::useSpotColor()}
 * can register it on a page under a stable resource name.
 *
 * Returned by {@see PdfDoc::registerSpotColor()}. Treat as opaque —
 * pass it to a page's `useSpotColor()` to obtain the resource name to
 * feed into `ContentStream::setFillColorSpace()` /
 * `setStrokeColorSpace()`.
 */
final class SpotColor
{
    public function __construct(
        public readonly string $name,
        public readonly Separation $separation,
    ) {}
}
