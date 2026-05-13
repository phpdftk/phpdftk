<?php

declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

chdir(__DIR__);

// region: example
use Phpdftk\Pdf\Core\Annotation\WidgetAnnotation;
use Phpdftk\Pdf\Core\Font\StandardFont;
use Phpdftk\Pdf\Core\Font\Type1Font;
use Phpdftk\Pdf\Core\Interactive\Form\AcroForm;
use Phpdftk\Pdf\Core\Interactive\Form\SignatureField;
use Phpdftk\Pdf\Core\Interactive\Signature\SignatureValue;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfString;
use Phpdftk\Pdf\Writer\PdfWriter;

// This example builds the structural object graph for a signable PDF — a signature
// field, a widget that exposes it on the page, and an AcroForm that flags the file
// as ready for signing. The placeholder /Contents will be patched by a real signer.
$writer = new PdfWriter();
$page = $writer->addPage();
$body = $writer->addFont(new Type1Font(StandardFont::Helvetica))->getResourceName();
$bold = $writer->addFont(new Type1Font(StandardFont::HelveticaBold))->getResourceName();

$cs = $writer->addContentStream($page);
$cs->beginText()->setFont($bold, 22)->moveTextPosition(72, 740)
    ->showText('Signable PDF')->endText();
$cs->beginText()->setFont($body, 11)->moveTextPosition(72, 700)
    ->showText('Below is an unsigned signature widget. A signing client patches the')
    ->endText();
$cs->beginText()->setFont($body, 11)->moveTextPosition(72, 684)
    ->showText('placeholder bytes to certify the document.')->endText();

// Placeholder SignatureValue — empty ByteRange and zeroed /Contents.
$sigValue = new SignatureValue();
$sigValue->name     = new PdfString('Sample Signer');
$sigValue->reason   = new PdfString('Showcase example');
$sigValue->location = new PdfString('phpdftk');
$sigValue->m        = new PdfString('D:20260512000000Z');
$sigValue->byteRange = new PdfArray([
    new PdfNumber(0), new PdfNumber(0), new PdfNumber(0), new PdfNumber(0),
]);
$sigValueRef = $writer->register($sigValue);

// Signature field bound to the placeholder.
$field = new SignatureField();
$field->t  = new PdfString('Signature1');
$field->tu = new PdfString('Author signature');
$field->setSignatureValue($sigValueRef);
$fieldRef = $writer->register($field);

// Widget that draws the signable box on the page.
$widget = new WidgetAnnotation(new PdfArray([
    new PdfNumber(72),  new PdfNumber(560),
    new PdfNumber(340), new PdfNumber(640),
]));
$widget->parent = $fieldRef;
$page->corePage()->annots[] = $writer->register($widget);

// Wire the form into the document and flag it as expecting a signature.
$form = new AcroForm();
$form->fields = [$fieldRef];
$form->sigFlags = 3;                 // SignaturesExist (1) | AppendOnly (2)
$writer->getCatalog()->acroForm = $writer->register($form);

$writer->save('signature-field.pdf');
// endregion

rename(__DIR__ . '/signature-field.pdf', example_output_path('core/interactive/signature-field.pdf'));
