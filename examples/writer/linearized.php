<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

chdir(__DIR__);

// region: example
use Phpdftk\Pdf\Core\Font\StandardFont;
use Phpdftk\Pdf\Core\Font\Type1Font;
use Phpdftk\Pdf\Writer\PdfWriter;

$writer = new PdfWriter();
$body = $writer->addFont(new Type1Font(StandardFont::Helvetica))->getResourceName();
$bold = $writer->addFont(new Type1Font(StandardFont::HelveticaBold))->getResourceName();

// First page rendered immediately on web display; subsequent pages stream in.
$first = $writer->addPage();
$cs = $writer->addContentStream($first);
$cs->beginText()->setFont($bold, 26)->moveTextPosition(72, 720)
    ->showText('Fast Web View Demo')->endText();
$cs->beginText()->setFont($body, 12)->moveTextPosition(72, 686)
    ->showText('This PDF is linearized — the first page renders before the')->endText();
$cs->beginText()->setFont($body, 12)->moveTextPosition(72, 670)
    ->showText('rest of the file finishes downloading (ISO 32000-2 Annex F).')->endText();

// A handful more pages so the linearization layout is meaningful.
for ($i = 2; $i <= 10; $i++) {
    $page = $writer->addPage();
    $cs = $writer->addContentStream($page);
    $cs->beginText()->setFont($bold, 18)->moveTextPosition(72, 720)
        ->showText(sprintf('Page %d', $i))->endText();
    $cs->beginText()->setFont($body, 11)->moveTextPosition(72, 690)
        ->showText('Loaded after the first page reaches the viewer.')->endText();
}

$writer->setLinearized(true);
$writer->save('linearized.pdf');
// endregion

rename(__DIR__ . '/linearized.pdf', example_output_path('writer/linearized.pdf'));
