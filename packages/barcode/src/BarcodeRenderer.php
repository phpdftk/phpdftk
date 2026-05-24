<?php

declare(strict_types=1);

namespace Phpdftk\Barcode;

use Phpdftk\Barcode\Encoder\Aztec;
use Phpdftk\Barcode\Encoder\Codabar;
use Phpdftk\Barcode\Encoder\Code128;
use Phpdftk\Barcode\Encoder\Code39;
use Phpdftk\Barcode\Encoder\DataMatrix;
use Phpdftk\Barcode\Encoder\Ean;
use Phpdftk\Barcode\Encoder\Itf;
use Phpdftk\Barcode\Encoder\Pdf417;
use Phpdftk\Barcode\Encoder\Qr;

/**
 * Top-level entry point: takes a {@see Symbology} + data string and
 * returns a {@see BarcodeBitmap}. Consumers (PDF / PNG / SVG renderers)
 * walk the bitmap and emit drawing primitives in their own coordinate
 * system.
 *
 * Implemented symbologies:
 *   - 1D: Code 128, Code 39, Codabar, ITF, EAN-13, EAN-8, UPC-A
 *   - 2D: QR, Data Matrix, PDF417, Aztec (Compact format, byte mode)
 */
final class BarcodeRenderer
{
    public static function render(Symbology $symbology, string $data, ?BarcodeOptions $options = null): BarcodeBitmap
    {
        $options ??= new BarcodeOptions();
        return match ($symbology) {
            Symbology::Code128 => Code128::encode($data, $options),
            Symbology::Code39 => Code39::encode($data, $options),
            Symbology::Codabar => Codabar::encode($data, $options),
            Symbology::ITF => Itf::encode($data, $options),
            Symbology::EAN13 => Ean::encodeEan13($data, $options),
            Symbology::EAN8 => Ean::encodeEan8($data, $options),
            Symbology::UPCA => Ean::encodeUpcA($data, $options),
            Symbology::QR => Qr::encode($data, $options),
            Symbology::DataMatrix => DataMatrix::encode($data, $options),
            Symbology::PDF417 => Pdf417::encode($data, $options),
            Symbology::Aztec => Aztec::encode($data, $options),
        };
    }
}
