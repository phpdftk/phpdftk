<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

chdir(__DIR__);

// region: example
use Phpdftk\Barcode\BarcodeOptions;
use Phpdftk\Barcode\Symbology;
use Phpdftk\Pdf\Writer\Alignment;
use Phpdftk\Pdf\Writer\Pdf;

$pdf = new Pdf();
$pdf->setTitle('Barcode demo — all supported symbologies');

$pdf->addHeading('Linear (1D) symbologies', 1);

$pdf->addHeading('Code 128 (printable ASCII)', 2);
$pdf->addBarcode(Symbology::Code128, 'HELLO WORLD');

$pdf->addHeading('Code 39 (digits + uppercase + a few symbols)', 2);
$pdf->addBarcode(Symbology::Code39, 'CODE-39 123');

$pdf->addHeading('Codabar (digits + symbols with A-D sentinels)', 2);
$pdf->addBarcode(Symbology::Codabar, 'A1234-5678B');

$pdf->addHeading('Interleaved 2 of 5 (digit pairs)', 2);
$pdf->addBarcode(Symbology::ITF, '12345678');

$pdf->newPage();
$pdf->addHeading('Retail symbologies (EAN / UPC)', 1);

$pdf->addHeading('EAN-13', 2);
$pdf->addText('Pass 12 digits (the checksum is computed for you) or 13 (verified).');
$pdf->addBarcode(Symbology::EAN13, '590123412345');

$pdf->addHeading('EAN-8', 2);
$pdf->addBarcode(Symbology::EAN8, '1234567');

$pdf->addHeading('UPC-A (North America)', 2);
$pdf->addBarcode(Symbology::UPCA, '72527273070');

$pdf->newPage();
$pdf->addHeading('Matrix (2D) symbologies', 1);

$pdf->addHeading('QR code', 2);
$pdf->addText('QR auto-selects the smallest version that fits at the requested ECC level. '
    . 'Numeric, alphanumeric, and byte modes are chosen automatically based on the input.');
$pdf->addBarcode(
    Symbology::QR,
    'https://phpdftk.dev/',
    new BarcodeOptions(moduleWidth: 3.0, quietZoneModules: 4),
    align: Alignment::Center,
);

$pdf->addHeading('Data Matrix', 2);
$pdf->addText('Data Matrix (ISO/IEC 16022, ECC 200) auto-selects from 24 square sizes. '
    . 'ASCII-mode encoding includes 2-digit pair compression that packs digit pairs into a single codeword.');
$pdf->addBarcode(
    Symbology::DataMatrix,
    'ORDER-12345-ABCDEF',
    new BarcodeOptions(moduleWidth: 4.0, quietZoneModules: 2),
    align: Alignment::Center,
);

$pdf->addHeading('PDF417', 2);
$pdf->addText('Multi-row stacked 2D symbology (ISO/IEC 15438). Byte Compaction mode encodes '
    . 'arbitrary 8-bit payloads. Row count / column count / ECC level are auto-selected.');
$pdf->addBarcode(
    Symbology::PDF417,
    'https://phpdftk.dev/',
    new BarcodeOptions(moduleWidth: 1.5, quietZoneModules: 2),
    align: Alignment::Center,
);

$pdf->addHeading('Aztec', 2);
$pdf->addText('Compact format only (1–4 layers, 15×15 to 27×27). Byte (Binary Shift) compaction '
    . 'encodes arbitrary 8-bit payloads. Layer count is auto-selected to fit payload + 23% ECC.');
$pdf->addBarcode(
    Symbology::Aztec,
    'https://phpdftk.dev/',
    new BarcodeOptions(moduleWidth: 3.0, quietZoneModules: 2),
    align: Alignment::Center,
);

$pdf->addHeading('Reusable template', 2);
$pdf->addText('createBarcode() returns a FormXObject you can stamp on every page without rebuilding.');
$template = $pdf->doc()->createBarcode(Symbology::QR, 'REUSE-ME', new BarcodeOptions(moduleWidth: 2.0));
$page = $pdf->doc()->addPage();
$page->drawTemplate($template, 72.0, 720.0);
$page->drawTemplate($template, 200.0, 720.0);
$page->drawTemplate($template, 328.0, 720.0);

$pdf->save('barcodes.pdf');
// endregion

rename(__DIR__ . '/barcodes.pdf', example_output_path('writer/barcodes.pdf'));
