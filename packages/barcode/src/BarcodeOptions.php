<?php

declare(strict_types=1);

namespace Phpdftk\Barcode;

/**
 * Options shared across all symbologies.
 *
 * For 1D barcodes, `$moduleWidth` controls the narrowest bar width
 * and `$height` is the bar height (both in renderer-agnostic units —
 * the consumer maps them to its coordinate system).
 *
 * For 2D barcodes, `$moduleWidth` is the size of one square module;
 * `$height` is ignored (2D barcodes are square or rectangular based
 * on the symbology).
 */
final class BarcodeOptions
{
    public function __construct(
        public readonly float $moduleWidth = 1.0,
        public readonly float $height = 30.0,
        /** Optional horizontal quiet zone (modules on each side). */
        public readonly int $quietZoneModules = 10,
    ) {}
}
