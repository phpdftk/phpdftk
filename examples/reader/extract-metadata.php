<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

chdir(__DIR__);

// region: example
use Phpdftk\Pdf\Core\Document\Info;
use Phpdftk\Pdf\Core\Font\StandardFont;
use Phpdftk\Pdf\Core\Font\Type1Font;
use Phpdftk\Pdf\Core\PdfString;
use Phpdftk\Pdf\Reader\PdfReader;
use Phpdftk\Pdf\Writer\PdfWriter;

// Step 1: write a small PDF with rich /Info metadata + a synced XMP packet.
$writer = new PdfWriter();
$page = $writer->addPage();
$writer->addFont(new Type1Font(StandardFont::Helvetica));
$writer->addContentStream($page)
    ->beginText()->setFont('F1', 18)->moveTextPosition(72, 720)
    ->showText('Document with rich metadata')->endText();

$info = new Info();
$info->title    = new PdfString('Q4 2026 Quarterly Report');
$info->author   = new PdfString('Finance Team');
$info->subject  = new PdfString('Revenue and expense summary');
$info->keywords = new PdfString('finance, quarterly, 2026, Q4');
$info->creator  = new PdfString('phpdftk metadata showcase');
$info->producer = new PdfString('phpdftk');
$info->creationDate = new PdfString('D:20260512000000Z');
$writer->setInfo($info);
$writer->syncInfoToMetadata();

$inputPdf = example_output_path('reader/metadata-source.pdf');
$writer->save($inputPdf);

// Step 2: read the same PDF back and extract its metadata.
$reader = PdfReader::fromFile($inputPdf);
$infoDict = $reader->getInfo();

$decode = static function (\Phpdftk\Pdf\Core\Serializable $value): string {
    if ($value instanceof \Phpdftk\Pdf\Core\PdfString) {
        return $value->value;
    }
    if ($value instanceof \Phpdftk\Pdf\Core\PdfName) {
        return $value->value;
    }
    return (string) $value;
};

$summary = [
    'pdfVersion' => $reader->getVersion(),
    'pageCount'  => $reader->getPageCount(),
    'linearized' => $reader->isLinearized(),
    'info'       => [],
];
foreach (['Title', 'Author', 'Subject', 'Keywords', 'Creator', 'Producer', 'CreationDate', 'ModDate'] as $key) {
    if ($infoDict?->has($key)) {
        $summary['info'][$key] = $decode($infoDict->get($key));
    }
}

file_put_contents(
    example_output_path('reader/metadata.json'),
    json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n",
);
// endregion
