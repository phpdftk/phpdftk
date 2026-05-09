<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

chdir(__DIR__);

// Setup: a filled-in form PDF (widgets are annotations that the flattener
// will bake into the page content stream).
{
    $writer = new Phpdftk\Pdf\Writer\PdfWriter(compressStreams: false);
    $page = $writer->addPage(612, 792);
    $writer->addFont(new Phpdftk\Pdf\Core\Font\Type1Font(Phpdftk\Pdf\Core\Font\StandardFont::Helvetica));

    $cs = $writer->addContentStream($page);
    $cs->beginText()->setFont('F1', 14)->moveTextPosition(72, 740)->showText('Membership Form')->endText();

    $field = new Phpdftk\Pdf\Core\Interactive\Form\TextField();
    $field->t = new Phpdftk\Pdf\Core\PdfString('full_name');
    $field->v = new Phpdftk\Pdf\Core\PdfString('Jane Doe');
    $field->da = new Phpdftk\Pdf\Core\PdfString('/F1 12 Tf 0 g');
    $writer->register($field);

    $widget = new Phpdftk\Pdf\Core\Annotation\WidgetAnnotation(new Phpdftk\Pdf\Core\PdfArray([
        new Phpdftk\Pdf\Core\PdfNumber(150), new Phpdftk\Pdf\Core\PdfNumber(700),
        new Phpdftk\Pdf\Core\PdfNumber(400), new Phpdftk\Pdf\Core\PdfNumber(720),
    ]));
    $widget->parent = new Phpdftk\Pdf\Core\PdfReference($field->objectNumber);
    $writer->register($widget);
    $field->kids = [new Phpdftk\Pdf\Core\PdfReference($widget->objectNumber)];
    $page->corePage()->annots[] = new Phpdftk\Pdf\Core\PdfReference($widget->objectNumber);

    $form = new Phpdftk\Pdf\Core\Interactive\Form\AcroForm();
    $form->fields = [new Phpdftk\Pdf\Core\PdfReference($field->objectNumber)];
    $form->needAppearances = true;
    $form->da = new Phpdftk\Pdf\Core\PdfString('/F1 12 Tf 0 g');
    $writer->register($form);
    $writer->getCatalog()->acroForm = new Phpdftk\Pdf\Core\PdfReference($form->objectNumber);

    file_put_contents('with-widgets.pdf', $writer->generate());
}

// region: example
use Phpdftk\Pdf\Toolkit\AnnotationFlattener;

// Bake widget annotations (form fields) into the page so the document
// renders identically everywhere — even in viewers that don't honor /AP.
AnnotationFlattener::open('with-widgets.pdf')
    ->flattenForms()
    ->save('flattened.pdf');

// Or: flatten every annotation, regardless of subtype.
// AnnotationFlattener::open('with-widgets.pdf')->flattenAll()->save('flat.pdf');
// endregion

rename(__DIR__ . '/with-widgets.pdf', example_output_path('toolkit/annotation-flattener/input.pdf'));
rename(__DIR__ . '/flattened.pdf', example_output_path('toolkit/annotation-flattener/output.pdf'));
