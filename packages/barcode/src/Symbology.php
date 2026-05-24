<?php

declare(strict_types=1);

namespace Phpdftk\Barcode;

/**
 * Supported barcode symbologies.
 *
 * Implemented:
 *   - 1D: `Code128`, `Code39`, `Codabar`, `ITF`, `EAN13`, `EAN8`, `UPCA`
 *   - 2D: `QR`, `DataMatrix`, `PDF417`, `Aztec` (Compact format, byte mode)
 */
enum Symbology: string
{
    case Code128 = 'code128';
    case Code39 = 'code39';
    case Codabar = 'codabar';
    case ITF = 'itf';
    case EAN13 = 'ean13';
    case EAN8 = 'ean8';
    case UPCA = 'upca';
    case QR = 'qr';
    case DataMatrix = 'datamatrix';

    // Reserved — see enum docblock.
    case PDF417 = 'pdf417';
    case Aztec = 'aztec';
}
