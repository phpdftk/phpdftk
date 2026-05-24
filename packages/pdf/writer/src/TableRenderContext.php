<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Writer;

use Phpdftk\FontMetrics\AfmData;

/**
 * Rendering context passed to {@see TableRenderer}. Bundles the body /
 * header font handles plus their metrics so the renderer doesn't need
 * to know how the caller resolved fonts.
 */
final class TableRenderContext
{
    public function __construct(
        public readonly Font $bodyFont,
        public readonly AfmData $bodyMetrics,
        public readonly Font $headerFont,
        public readonly AfmData $headerMetrics,
        public readonly float $fontSize,
        public readonly float $lineHeight,
        public readonly TableStyle $style,
    ) {}
}
