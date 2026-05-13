<?php

declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

chdir(__DIR__);

// region: example
use Phpdftk\Pdf\Core\Annotation\WidgetAnnotation;
use Phpdftk\Pdf\Core\Font\StandardFont;
use Phpdftk\Pdf\Core\Font\Type1Font;
use Phpdftk\Pdf\Core\Interactive\Form\AcroForm;
use Phpdftk\Pdf\Core\Interactive\Form\ButtonField;
use Phpdftk\Pdf\Core\Interactive\Form\ChoiceField;
use Phpdftk\Pdf\Core\Interactive\Form\TextField;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfString;
use Phpdftk\Pdf\Writer\PdfWriter;

$writer = new PdfWriter();
$page = $writer->addPage();
$writer->addFont(new Type1Font(StandardFont::Helvetica));   // becomes F1
$writer->addFont(new Type1Font(StandardFont::HelveticaBold)); // becomes F2

// Page labels for each field.
$cs = $writer->addContentStream($page);
$cs->beginText()->setFont('F2', 22)->moveTextPosition(72, 740)
    ->showText('Interactive Form Widgets')->endText();

$labels = [
    [690, 'Full name:'],
    [630, 'Email address:'],
    [570, 'Subscribe to newsletter:'],
    [510, 'Country:'],
    [450, 'Notes:'],
];
foreach ($labels as [$y, $text]) {
    $cs->beginText()->setFont('F1', 12)->moveTextPosition(72, $y)
        ->showText($text)->endText();
}

// Helper: register a field plus its widget and attach to the page.
$fields = [];
$attachField = function (object $field, float $x1, float $y1, float $x2, float $y2)
    use ($writer, $page, &$fields): void
{
    $writer->register($field);

    $widget = new WidgetAnnotation(new PdfArray([
        new PdfNumber($x1), new PdfNumber($y1), new PdfNumber($x2), new PdfNumber($y2),
    ]));
    $widget->parent = new PdfReference($field->objectNumber);
    $writer->register($widget);
    $page->corePage()->annots[] = new PdfReference($widget->objectNumber);
    $fields[] = new PdfReference($field->objectNumber);
};

// 1) Name — single-line text field
$name = new TextField();
$name->t  = new PdfString('name');
$name->tu = new PdfString('Full Name');
$name->maxLen = 100;
$attachField($name, 220, 685, 500, 705);

// 2) Email — single-line text field with default value
$email = new TextField();
$email->t  = new PdfString('email');
$email->tu = new PdfString('Email Address');
$email->v  = new PdfString('you@example.com');
$attachField($email, 220, 625, 500, 645);

// 3) Subscribe — checkbox (button field with no bits set)
$subscribe = new ButtonField();
$subscribe->t  = new PdfString('subscribe');
$subscribe->tu = new PdfString('Subscribe to newsletter');
$subscribe->ff = 0;
$attachField($subscribe, 220, 568, 235, 583);

// 4) Country — combo-box choice field
$country = new ChoiceField();
$country->t  = new PdfString('country');
$country->tu = new PdfString('Country of residence');
$country->ff = 1 << 17;                       // combo flag
$country->opt = new PdfArray([
    new PdfString('United States'),
    new PdfString('Canada'),
    new PdfString('United Kingdom'),
    new PdfString('Australia'),
    new PdfString('Other'),
]);
$attachField($country, 220, 505, 500, 525);

// 5) Notes — multiline text field (bit 13 = multiline)
$notes = new TextField();
$notes->t  = new PdfString('notes');
$notes->tu = new PdfString('Free-form notes');
$notes->ff = 1 << 12;                         // multiline flag
$attachField($notes, 220, 360, 500, 460);

// Wire all fields into a single AcroForm.
$form = new AcroForm();
$form->fields = $fields;
$form->needAppearances = true;
$form->da = new PdfString('/F1 12 Tf 0 g');

$writer->register($form);
$writer->getCatalog()->acroForm = new PdfReference($form->objectNumber);

$writer->save('form-widgets.pdf');
// endregion

rename(__DIR__ . '/form-widgets.pdf', example_output_path('core/interactive/form-widgets.pdf'));
