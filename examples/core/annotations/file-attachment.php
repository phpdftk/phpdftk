<?php

declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

chdir(__DIR__);

// region: example
use Phpdftk\Pdf\Core\Annotation\FileAttachmentAnnotation;
use Phpdftk\Pdf\Core\FileSpec\EmbeddedFile;
use Phpdftk\Pdf\Core\FileSpec\FileSpec;
use Phpdftk\Pdf\Core\Font\StandardFont;
use Phpdftk\Pdf\Core\Font\Type1Font;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfString;
use Phpdftk\Pdf\Writer\PdfWriter;

$writer = new PdfWriter();
$page = $writer->addPage();
$body = $writer->addFont(new Type1Font(StandardFont::Helvetica))->getResourceName();
$bold = $writer->addFont(new Type1Font(StandardFont::HelveticaBold))->getResourceName();

$cs = $writer->addContentStream($page);
$cs->beginText()->setFont($bold, 22)->moveTextPosition(72, 740)
    ->showText('File Attachment Annotations')->endText();
$cs->beginText()->setFont($body, 11)->moveTextPosition(72, 710)
    ->showText('Each paperclip below is a real embedded file. Click to download in any viewer.')
    ->endText();

$attachments = [
    ['hello.txt',    'Plain text greeting',     "Hello from a real embedded file!\n"],
    ['readme.md',    'Markdown notes',          "# Notes\n\n- The host PDF is the container.\n- This file rides inside.\n"],
    ['data.csv',     'CSV data table',          "name,score\nAda,42\nGrace,73\nMargaret,99\n"],
];

$y = 660;
foreach ($attachments as [$filename, $label, $payload]) {
    // 1) Embed the bytes as an EmbeddedFile stream.
    $embedded = new EmbeddedFile($payload);
    $writer->register($embedded);

    // 2) Wrap the stream in a FileSpec that names the embedded file.
    $fs = new FileSpec($filename);
    $fs->desc = new PdfString($label);
    $fs->attachEmbeddedFile(new PdfReference($embedded->objectNumber));
    $writer->register($fs);

    // 3) Place a FileAttachment annotation on the page that references the FileSpec.
    $rect = new PdfArray([
        new PdfNumber(72), new PdfNumber($y - 4),
        new PdfNumber(92), new PdfNumber($y + 16),
    ]);
    $annot = new FileAttachmentAnnotation($rect);
    $annot->name = new PdfName('Paperclip');
    $annot->contents = new PdfString($label);
    $annot->fs = new PdfReference($fs->objectNumber);
    $writer->register($annot);
    $page->corePage()->annots[] = new PdfReference($annot->objectNumber);

    // 4) Label the attachment so the page reads well even without hovering.
    $cs->beginText()->setFont($bold, 11)->moveTextPosition(110, $y)
        ->showText($filename)->endText();
    $cs->beginText()->setFont($body, 11)->moveTextPosition(220, $y)
        ->showText($label)->endText();

    $y -= 36;
}

$writer->save('file-attachment.pdf');
// endregion

rename(__DIR__ . '/file-attachment.pdf', example_output_path('core/annotations/file-attachment.pdf'));
