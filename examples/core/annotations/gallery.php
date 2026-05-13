<?php

declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

chdir(__DIR__);

// region: example
use Phpdftk\Pdf\Core\Annotation\FileAttachmentAnnotation;
use Phpdftk\Pdf\Core\Annotation\HighlightAnnotation;
use Phpdftk\Pdf\Core\Annotation\InkAnnotation;
use Phpdftk\Pdf\Core\Annotation\LinkAnnotation;
use Phpdftk\Pdf\Core\Annotation\StampAnnotation;
use Phpdftk\Pdf\Core\Annotation\TextAnnotation;
use Phpdftk\Pdf\Core\FileSpec\EmbeddedFile;
use Phpdftk\Pdf\Core\FileSpec\FileSpec;
use Phpdftk\Pdf\Core\Font\StandardFont;
use Phpdftk\Pdf\Core\Font\Type1Font;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfString;
use Phpdftk\Pdf\Writer\PdfWriter;

$writer = new PdfWriter();
$page = $writer->addPage();
$body = $writer->addFont(new Type1Font(StandardFont::Helvetica));
$bold = $writer->addFont(new Type1Font(StandardFont::HelveticaBold));

// Helper: build a /Rect array.
$rect = static fn (float $x1, float $y1, float $x2, float $y2) => new PdfArray([
    new PdfNumber($x1), new PdfNumber($y1), new PdfNumber($x2), new PdfNumber($y2),
]);

// Helper: build a /QuadPoints array describing one rectangle.
$quad = static fn (float $x1, float $y1, float $x2, float $y2) => new PdfArray([
    new PdfNumber($x1), new PdfNumber($y2), new PdfNumber($x2), new PdfNumber($y2),
    new PdfNumber($x1), new PdfNumber($y1), new PdfNumber($x2), new PdfNumber($y1),
]);

$attach = static function (object $annot) use ($writer, $page): void {
    $writer->register($annot);
    $page->corePage()->annots[] = new PdfReference($annot->objectNumber);
};

// Page labels and descriptive text.
$cs = $writer->addContentStream($page);
$cs->beginText()->setFont($bold, 22)->moveTextPosition(72, 740)
    ->showText('Annotation Gallery')->endText();

$labels = [
    [700, 'Sticky note — click the icon to expand.'],
    [640, 'Highlighted text — visible in any PDF viewer.'],
    [580, 'Click this link to open phpdftk.dev ->'],
    [520, 'Approved stamp (below):'],
    [440, 'Ink scribble (freehand annotation):'],
    [340, 'Paperclip — embedded text file attached.'],
];
foreach ($labels as [$y, $text]) {
    $cs->beginText()->setFont($body, 11)->moveTextPosition(72, $y)
        ->showText($text)->endText();
}

// 1. Sticky note (TextAnnotation) ----------------------------------------
$note = new TextAnnotation($rect(330, 695, 350, 715));
$note->contents = new PdfString('A reviewer left this note for you.');
$note->name = new PdfName('Note');
$note->open = false;
$note->c = new PdfArray([new PdfNumber(1), new PdfNumber(0.85), new PdfNumber(0.2)]);
$attach($note);

// 2. Highlight over an imagined text run ---------------------------------
$highlight = new HighlightAnnotation(
    $rect(72, 632, 380, 652),
    $quad(72, 632, 380, 652),
);
$highlight->contents = new PdfString('Highlighted passage');
$highlight->c = new PdfArray([new PdfNumber(1), new PdfNumber(1), new PdfNumber(0)]);
$attach($highlight);

// 3. Link annotation with a URI action -----------------------------------
$link = new LinkAnnotation($rect(72, 572, 280, 588));
$link->h = new PdfName('I'); // invert highlight on click
$link->a = new PdfDictionary([
    'Type' => new PdfName('Action'),
    'S'    => new PdfName('URI'),
    'URI'  => new PdfString('https://phpdftk.dev'),
]);
$attach($link);

// 4. Approved stamp ------------------------------------------------------
$stamp = new StampAnnotation($rect(220, 500, 380, 540));
$stamp->name = new PdfName('Approved');
$stamp->contents = new PdfString('Approved by the editorial team.');
$attach($stamp);

// 5. Ink annotation — a hand-drawn wavy line -----------------------------
$inkPath = [];
for ($x = 72; $x <= 360; $x += 8) {
    $inkPath[] = new PdfNumber($x);
    $inkPath[] = new PdfNumber(400 + 10 * sin(($x - 72) / 18));
}
$ink = new InkAnnotation($rect(72, 380, 360, 420), new PdfArray([new PdfArray($inkPath)]));
$ink->contents = new PdfString('Hand-drawn ink scribble.');
$ink->c = new PdfArray([new PdfNumber(0.1), new PdfNumber(0.4), new PdfNumber(0.85)]);
$attach($ink);

// 6. File attachment — embed a small text file beside a paperclip --------
$embedded = new EmbeddedFile("Hello from a real embedded file!\n");
$writer->register($embedded);
$fileSpec = new FileSpec('hello.txt');
$fileSpec->ef = new PdfDictionary([
    'F' => new PdfReference($embedded->objectNumber),
]);
$writer->register($fileSpec);

$attachment = new FileAttachmentAnnotation($rect(72, 310, 92, 330));
$attachment->name = new PdfName('Paperclip');
$attachment->contents = new PdfString('Click to download hello.txt');
$attachment->fs = new PdfReference($fileSpec->objectNumber);
$attach($attachment);

$writer->save('gallery.pdf');
// endregion

rename(__DIR__ . '/gallery.pdf', example_output_path('core/annotations/gallery.pdf'));
