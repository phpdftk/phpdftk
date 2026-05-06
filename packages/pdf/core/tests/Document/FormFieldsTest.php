<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Tests\Document;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Phpdftk\Pdf\Core\Annotation\WidgetAnnotation;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfString;
use Phpdftk\Pdf\Core\Font\StandardFont;
use Phpdftk\Pdf\Core\Font\Type1Font;
use Phpdftk\Pdf\Core\Interactive\Form\AcroForm;
use Phpdftk\Pdf\Core\Interactive\Form\ButtonField;
use Phpdftk\Pdf\Core\Interactive\Form\ChoiceField;
use Phpdftk\Pdf\Core\Interactive\Form\TextField;
use Phpdftk\Pdf\Writer\PdfWriter;
use Phpdftk\Tests\Support\Arlington\ArlingtonValidationTrait;
use Phpdftk\Tests\Support\QpdfValidationTrait;

/**
 * Generates a PDF with an interactive AcroForm containing
 * text, checkbox, and choice fields.
 */
#[Group("qpdf")]
#[Group("arlington")]
class FormFieldsTest extends TestCase
{
    use ArlingtonValidationTrait;
    use QpdfValidationTrait;
    private const OUTPUT_FILE = __DIR__ . '/../../../../../docs/sample-pdfs/form_fields.pdf';

    public function testGeneratesFormPdf(): void
    {
        $writer = new PdfWriter();
        $page   = $writer->addPage(612, 792);
        $writer->addFont(new Type1Font(StandardFont::Helvetica));

        // ----------------------------------------------------------------
        // Page content: labels
        // ----------------------------------------------------------------
        $cs = $writer->addContentStream($page);
        $cs->beginText()
           ->setFont('F1', 16)
           ->moveTextPosition(72, 750)
           ->showText('Interactive Form Test')
           ->moveTextPosition(0, -50)
           ->setFont('F1', 12)
           ->showText('Name:')
           ->moveTextPosition(0, -50)
           ->showText('Subscribe:')
           ->moveTextPosition(0, -50)
           ->showText('Country:')
           ->endText();

        // ----------------------------------------------------------------
        // Text field
        // ----------------------------------------------------------------
        $nameField = new TextField();
        $nameField->t = new PdfString('name');
        $nameField->tu = new PdfString('Full Name');
        $nameField->maxLen = 100;

        $writer->register($nameField);

        // Widget annotation for the text field
        $nameWidget = new WidgetAnnotation(
            new PdfArray([
                new PdfNumber(150),
                new PdfNumber(685),
                new PdfNumber(400),
                new PdfNumber(705),
            ]),
        );
        $nameWidget->parent = new PdfReference($nameField->objectNumber);
        $writer->register($nameWidget);
        $page->corePage()->annots[] = new PdfReference($nameWidget->objectNumber);

        // ----------------------------------------------------------------
        // Button field (checkbox)
        // ----------------------------------------------------------------
        $checkField = new ButtonField();
        $checkField->t = new PdfString('subscribe');
        $checkField->tu = new PdfString('Subscribe to newsletter');
        // Bit 16 = pushbutton, bit 17 = radio — neither set = checkbox
        $checkField->ff = 0;

        $writer->register($checkField);

        $checkWidget = new WidgetAnnotation(
            new PdfArray([
                new PdfNumber(150),
                new PdfNumber(635),
                new PdfNumber(165),
                new PdfNumber(650),
            ]),
        );
        $checkWidget->parent = new PdfReference($checkField->objectNumber);
        $writer->register($checkWidget);
        $page->corePage()->annots[] = new PdfReference($checkWidget->objectNumber);

        // ----------------------------------------------------------------
        // Choice field (combo box / dropdown)
        // ----------------------------------------------------------------
        $countryField = new ChoiceField();
        $countryField->t = new PdfString('country');
        $countryField->tu = new PdfString('Country of residence');
        // Bit 18 = combo (drop-down list)
        $countryField->ff = 1 << 17; // combo box flag
        $countryField->opt = new PdfArray([
            new PdfString('United States'),
            new PdfString('Canada'),
            new PdfString('United Kingdom'),
            new PdfString('Australia'),
            new PdfString('Other'),
        ]);

        $writer->register($countryField);

        $countryWidget = new WidgetAnnotation(
            new PdfArray([
                new PdfNumber(150),
                new PdfNumber(585),
                new PdfNumber(400),
                new PdfNumber(605),
            ]),
        );
        $countryWidget->parent = new PdfReference($countryField->objectNumber);
        $writer->register($countryWidget);
        $page->corePage()->annots[] = new PdfReference($countryWidget->objectNumber);

        // ----------------------------------------------------------------
        // AcroForm dictionary
        // ----------------------------------------------------------------
        $acroForm = new AcroForm();
        $acroForm->fields = [
            new PdfReference($nameField->objectNumber),
            new PdfReference($checkField->objectNumber),
            new PdfReference($countryField->objectNumber),
        ];
        $acroForm->needAppearances = true;
        $acroForm->da = new PdfString('/F1 12 Tf 0 g');

        $writer->register($acroForm);
        $writer->getCatalog()->acroForm = new PdfReference($acroForm->objectNumber);

        // ----------------------------------------------------------------
        // Save and validate
        // ----------------------------------------------------------------
        $writer->save(self::OUTPUT_FILE);

        self::assertFileExists(self::OUTPUT_FILE);
        $this->assertQpdfValid(self::OUTPUT_FILE);
        $this->assertArlingtonValid(self::OUTPUT_FILE);

        $content = file_get_contents(self::OUTPUT_FILE);
        self::assertNotFalse($content);
        self::assertStringStartsWith('%PDF-', $content);
        self::assertStringContainsString('%%EOF', $content);
        // Check form field types appear
        self::assertStringContainsString('/FT /Tx', $content);   // text field
        self::assertStringContainsString('/FT /Btn', $content);  // button
        self::assertStringContainsString('/FT /Ch', $content);   // choice
        self::assertStringContainsString('/AcroForm', $content);
        self::assertStringContainsString('/NeedAppearances true', $content);
    }
}
