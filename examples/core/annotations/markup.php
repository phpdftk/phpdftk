<?php

declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

chdir(__DIR__);

// region: example
use Phpdftk\Pdf\Core\Annotation\HighlightAnnotation;
use Phpdftk\Pdf\Core\Annotation\SquigglyAnnotation;
use Phpdftk\Pdf\Core\Annotation\StrikeOutAnnotation;
use Phpdftk\Pdf\Core\Annotation\UnderlineAnnotation;
use Phpdftk\Pdf\Core\Font\StandardFont;
use Phpdftk\Pdf\Core\Font\Type1Font;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfString;
use Phpdftk\Pdf\Writer\PdfWriter;

$writer = new PdfWriter();
$page = $writer->addPage();
$body = $writer->addFont(new Type1Font(StandardFont::Helvetica))->getResourceName();
$bold = $writer->addFont(new Type1Font(StandardFont::HelveticaBold))->getResourceName();

// Page header.
$cs = $writer->addContentStream($page);
$cs->beginText()->setFont($bold, 22)->moveTextPosition(72, 740)
    ->showText('Text Markup Annotations')->endText();

// Four labeled lines, each one will get a markup overlay.
$lines = [
    [680, 'Highlight', 'A highlight marks important text in yellow.'],
    [620, 'Underline', 'Underline draws a line below the marked text.'],
    [560, 'Strike-out', 'Strike-out crosses out text without removing it.'],
    [500, 'Squiggly', 'Squiggly underlines for spelling or grammar.'],
];

foreach ($lines as [$y, $label, $text]) {
    $cs->beginText()->setFont($bold, 11)->moveTextPosition(72, $y)
        ->showText($label . ':')->endText();
    $cs->beginText()->setFont($body, 12)->moveTextPosition(160, $y)
        ->showText($text)->endText();
}

$rect = static fn (float $x1, float $y1, float $x2, float $y2) => new PdfArray([
    new PdfNumber($x1), new PdfNumber($y1), new PdfNumber($x2), new PdfNumber($y2),
]);
$quad = static fn (float $x1, float $y1, float $x2, float $y2) => new PdfArray([
    new PdfNumber($x1), new PdfNumber($y2), new PdfNumber($x2), new PdfNumber($y2),
    new PdfNumber($x1), new PdfNumber($y1), new PdfNumber($x2), new PdfNumber($y1),
]);
$attach = static function (object $annot) use ($writer, $page): void {
    $writer->register($annot);
    $page->corePage()->annots[] = new PdfReference($annot->objectNumber);
};

// Apply one markup type per line at the line's baseline.
$markups = [
    HighlightAnnotation::class => 680,
    UnderlineAnnotation::class => 620,
    StrikeOutAnnotation::class => 560,
    SquigglyAnnotation::class  => 500,
];

foreach ($markups as $class => $y) {
    $r = $rect(160, $y - 2, 460, $y + 12);
    $q = $quad(160, $y - 2, 460, $y + 12);
    // Highlight requires quadPoints in its constructor; the others expose it as a property.
    $annot = $class === HighlightAnnotation::class ? new $class($r, $q) : new $class($r);
    if (property_exists($annot, 'quadPoints') && $annot->quadPoints === null) {
        $annot->quadPoints = $q;
    }
    $annot->contents = new PdfString(str_replace('Annotation', '', substr($class, strrpos($class, '\\') + 1)));
    $attach($annot);
}

$writer->save('markup.pdf');
// endregion

rename(__DIR__ . '/markup.pdf', example_output_path('core/annotations/markup.pdf'));
