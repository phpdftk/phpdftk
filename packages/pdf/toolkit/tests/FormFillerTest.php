<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Toolkit\Tests;

use Phpdftk\Pdf\Core\Annotation\WidgetAnnotation;
use Phpdftk\Pdf\Core\Font\StandardFont;
use Phpdftk\Pdf\Core\Font\Type1Font;
use Phpdftk\Pdf\Core\Interactive\Form\AcroForm;
use Phpdftk\Pdf\Core\Interactive\Form\ButtonField;
use Phpdftk\Pdf\Core\Interactive\Form\ChoiceField;
use Phpdftk\Pdf\Core\Interactive\Form\TextField;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfString;
use Phpdftk\Pdf\Reader\PdfReader;
use Phpdftk\Pdf\Toolkit\Form\FieldInfo;
use Phpdftk\Pdf\Toolkit\Form\FieldType;
use Phpdftk\Pdf\Toolkit\FormFiller;
use Phpdftk\Pdf\Writer\PdfWriter;
use Phpdftk\Tests\Support\QpdfValidationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group("qpdf")]
class FormFillerTest extends TestCase
{
    use QpdfValidationTrait;
    /**
     * Generate a test PDF with text, checkbox, and choice fields.
     *
     * Uses the separate field + widget pattern from FormFieldsTest.
     */
    private function generateFormPdf(): string
    {
        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $writer->addFont(new Type1Font(StandardFont::Helvetica));

        $cs = $writer->addContentStream($page);
        $cs->beginText()
            ->setFont('F1', 12)
            ->moveTextPosition(72, 720)
            ->showText('Form Test')
            ->endText();

        // Text field
        $nameField = new TextField();
        $nameField->t = new PdfString('name');
        $nameField->da = new PdfString('/F1 12 Tf 0 g');
        $writer->register($nameField);

        $nameWidget = new WidgetAnnotation(
            new PdfArray([
                new PdfNumber(150),
                new PdfNumber(685),
                new PdfNumber(400),
                new PdfNumber(705),
            ])
        );
        $nameWidget->parent = new PdfReference($nameField->objectNumber);
        $writer->register($nameWidget);
        $nameField->kids = [new PdfReference($nameWidget->objectNumber)];
        $page->corePage()->annots[] = new PdfReference($nameWidget->objectNumber);

        // Text field with maxLen
        $emailField = new TextField();
        $emailField->t = new PdfString('email');
        $emailField->maxLen = 50;
        $emailField->da = new PdfString('/F1 12 Tf 0 g');
        $writer->register($emailField);

        $emailWidget = new WidgetAnnotation(
            new PdfArray([
                new PdfNumber(150),
                new PdfNumber(635),
                new PdfNumber(400),
                new PdfNumber(655),
            ])
        );
        $emailWidget->parent = new PdfReference($emailField->objectNumber);
        $writer->register($emailWidget);
        $emailField->kids = [new PdfReference($emailWidget->objectNumber)];
        $page->corePage()->annots[] = new PdfReference($emailWidget->objectNumber);

        // Checkbox (button field)
        $checkField = new ButtonField();
        $checkField->t = new PdfString('subscribe');
        $checkField->ff = 0; // no pushbutton, no radio = checkbox
        $writer->register($checkField);

        $checkWidget = new WidgetAnnotation(
            new PdfArray([
                new PdfNumber(150),
                new PdfNumber(585),
                new PdfNumber(165),
                new PdfNumber(600),
            ])
        );
        $checkWidget->parent = new PdfReference($checkField->objectNumber);
        $writer->register($checkWidget);
        $checkField->kids = [new PdfReference($checkWidget->objectNumber)];
        $page->corePage()->annots[] = new PdfReference($checkWidget->objectNumber);

        // Choice field (combo box)
        $countryField = new ChoiceField();
        $countryField->t = new PdfString('country');
        $countryField->ff = 1 << 17; // combo box flag
        $countryField->opt = new PdfArray([
            new PdfString('United States'),
            new PdfString('Canada'),
            new PdfString('United Kingdom'),
        ]);
        $writer->register($countryField);

        $countryWidget = new WidgetAnnotation(
            new PdfArray([
                new PdfNumber(150),
                new PdfNumber(535),
                new PdfNumber(400),
                new PdfNumber(555),
            ])
        );
        $countryWidget->parent = new PdfReference($countryField->objectNumber);
        $writer->register($countryWidget);
        $countryField->kids = [new PdfReference($countryWidget->objectNumber)];
        $page->corePage()->annots[] = new PdfReference($countryWidget->objectNumber);

        // AcroForm
        $acroForm = new AcroForm();
        $acroForm->fields = [
            new PdfReference($nameField->objectNumber),
            new PdfReference($emailField->objectNumber),
            new PdfReference($checkField->objectNumber),
            new PdfReference($countryField->objectNumber),
        ];
        $acroForm->needAppearances = true;
        $acroForm->da = new PdfString('/F1 12 Tf 0 g');

        $writer->register($acroForm);
        $writer->getCatalog()->acroForm = new PdfReference($acroForm->objectNumber);

        return $writer->generate();
    }

    public function testOpenString(): void
    {
        $pdf = $this->generateFormPdf();
        $filler = FormFiller::openString($pdf);
        $this->assertSame(1, $filler->getPageCount());
    }

    public function testGetFieldNames(): void
    {
        $pdf = $this->generateFormPdf();
        $filler = FormFiller::openString($pdf);

        $names = $filler->getFieldNames();
        $this->assertContains('name', $names);
        $this->assertContains('email', $names);
        $this->assertContains('subscribe', $names);
        $this->assertContains('country', $names);
        $this->assertCount(4, $names);
    }

    public function testHasField(): void
    {
        $pdf = $this->generateFormPdf();
        $filler = FormFiller::openString($pdf);

        $this->assertTrue($filler->hasField('name'));
        $this->assertTrue($filler->hasField('country'));
        $this->assertFalse($filler->hasField('nonexistent'));
    }

    public function testGetFieldInfo(): void
    {
        $pdf = $this->generateFormPdf();
        $filler = FormFiller::openString($pdf);

        $info = $filler->getFieldInfo('name');
        $this->assertNotNull($info);
        $this->assertSame('name', $info->name);
        $this->assertSame(FieldType::Text, $info->type);
        $this->assertNull($info->value);

        $emailInfo = $filler->getFieldInfo('email');
        $this->assertNotNull($emailInfo);
        $this->assertSame(FieldType::Text, $emailInfo->type);
        $this->assertSame(50, $emailInfo->maxLen);

        $subInfo = $filler->getFieldInfo('subscribe');
        $this->assertNotNull($subInfo);
        $this->assertSame(FieldType::Button, $subInfo->type);

        $countryInfo = $filler->getFieldInfo('country');
        $this->assertNotNull($countryInfo);
        $this->assertSame(FieldType::Choice, $countryInfo->type);
        $this->assertNotNull($countryInfo->options);
        $this->assertContains('Canada', $countryInfo->options);
    }

    public function testGetFieldInfoReturnsNullForMissing(): void
    {
        $pdf = $this->generateFormPdf();
        $filler = FormFiller::openString($pdf);

        $this->assertNull($filler->getFieldInfo('nonexistent'));
    }

    public function testGetFieldValues(): void
    {
        $pdf = $this->generateFormPdf();
        $filler = FormFiller::openString($pdf);

        $values = $filler->getFieldValues();
        $this->assertArrayHasKey('name', $values);
        $this->assertArrayHasKey('email', $values);
        $this->assertArrayHasKey('subscribe', $values);
        $this->assertArrayHasKey('country', $values);
        // All values null initially
        $this->assertNull($values['name']);
    }

    public function testFillTextField(): void
    {
        $pdf = $this->generateFormPdf();
        $filler = FormFiller::openString($pdf);

        $result = $filler->fill('name', 'Jane Doe');
        $this->assertSame($filler, $result, 'fill() should return $this for chaining');

        $bytes = $filler->toBytes();
        $this->assertStringStartsWith('%PDF-', $bytes);
        $this->assertQpdfValidBytes($bytes);

        // Re-read and verify the value was set
        $reader = PdfReader::fromString($bytes);
        $this->assertFormFieldValue($reader, 'name', 'Jane Doe');
    }

    public function testFillManyFields(): void
    {
        $pdf = $this->generateFormPdf();
        $filled = FormFiller::openString($pdf)
            ->fillMany([
                'name' => 'John Smith',
                'email' => 'john@example.com',
            ])
            ->toBytes();

        $this->assertQpdfValidBytes($filled);
        $reader = PdfReader::fromString($filled);
        $this->assertFormFieldValue($reader, 'name', 'John Smith');
        $this->assertFormFieldValue($reader, 'email', 'john@example.com');
    }

    public function testCheckCheckbox(): void
    {
        $pdf = $this->generateFormPdf();
        $filled = FormFiller::openString($pdf)
            ->check('subscribe')
            ->toBytes();

        $this->assertQpdfValidBytes($filled);
        $reader = PdfReader::fromString($filled);
        $this->assertFormFieldValue($reader, 'subscribe', 'Yes');
    }

    public function testUncheckCheckbox(): void
    {
        $pdf = $this->generateFormPdf();
        $filled = FormFiller::openString($pdf)
            ->check('subscribe', false)
            ->toBytes();

        $this->assertQpdfValidBytes($filled);
        $reader = PdfReader::fromString($filled);
        $this->assertFormFieldValue($reader, 'subscribe', 'Off');
    }

    public function testSelectChoiceField(): void
    {
        $pdf = $this->generateFormPdf();
        $filled = FormFiller::openString($pdf)
            ->select('country', 'Canada')
            ->toBytes();

        $this->assertQpdfValidBytes($filled);
        $reader = PdfReader::fromString($filled);
        $this->assertFormFieldValue($reader, 'country', 'Canada');
    }

    public function testFillMultipleFieldTypes(): void
    {
        $pdf = $this->generateFormPdf();
        $filled = FormFiller::openString($pdf)
            ->fill('name', 'Jane Doe')
            ->check('subscribe')
            ->select('country', 'United Kingdom')
            ->toBytes();

        $this->assertQpdfValidBytes($filled);
        $reader = PdfReader::fromString($filled);
        $this->assertFormFieldValue($reader, 'name', 'Jane Doe');
        $this->assertFormFieldValue($reader, 'subscribe', 'Yes');
        $this->assertFormFieldValue($reader, 'country', 'United Kingdom');
    }

    public function testFillThrowsForUnknownField(): void
    {
        $pdf = $this->generateFormPdf();
        $filler = FormFiller::openString($pdf);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Field not found: nonexistent');
        $filler->fill('nonexistent', 'value');
    }

    public function testCheckThrowsForUnknownField(): void
    {
        $pdf = $this->generateFormPdf();
        $filler = FormFiller::openString($pdf);

        $this->expectException(\InvalidArgumentException::class);
        $filler->check('nonexistent');
    }

    public function testSelectThrowsForUnknownField(): void
    {
        $pdf = $this->generateFormPdf();
        $filler = FormFiller::openString($pdf);

        $this->expectException(\InvalidArgumentException::class);
        $filler->select('nonexistent', 'option');
    }

    public function testToBytesWithNoChangesReturnsOriginal(): void
    {
        $pdf = $this->generateFormPdf();
        $filler = FormFiller::openString($pdf);

        $bytes = $filler->toBytes();
        $this->assertSame($pdf, $bytes);
    }

    public function testSaveWritesFile(): void
    {
        $pdf = $this->generateFormPdf();
        $outPath = sys_get_temp_dir() . '/phpdftk_form_filler_test_' . uniqid() . '.pdf';

        try {
            FormFiller::openString($pdf)
                ->fill('name', 'Test User')
                ->save($outPath);

            $this->assertFileExists($outPath);
            $content = file_get_contents($outPath);
            $this->assertStringStartsWith('%PDF-', $content);
            $this->assertQpdfValid($outPath);
        } finally {
            if (file_exists($outPath)) {
                unlink($outPath);
            }
        }
    }

    public function testEscapeHatch(): void
    {
        $pdf = $this->generateFormPdf();
        $filler = FormFiller::openString($pdf);

        $reader = $filler->getReader();
        $this->assertInstanceOf(PdfReader::class, $reader);
        $this->assertSame(1, $reader->getPageCount());
    }

    public function testRoundTripPreservesOtherFields(): void
    {
        $pdf = $this->generateFormPdf();

        // Fill only 'name', then re-read and verify all other fields still exist
        $filled = FormFiller::openString($pdf)
            ->fill('name', 'Alice')
            ->toBytes();

        $this->assertQpdfValidBytes($filled);
        $filler2 = FormFiller::openString($filled);
        $names = $filler2->getFieldNames();
        $this->assertContains('name', $names);
        $this->assertContains('email', $names);
        $this->assertContains('subscribe', $names);
        $this->assertContains('country', $names);

        $info = $filler2->getFieldInfo('name');
        $this->assertSame('Alice', $info->value);
    }

    // -----------------------------------------------------------------------
    // Helper: find a field value in the re-read PDF
    // -----------------------------------------------------------------------

    private function assertFormFieldValue(PdfReader $reader, string $fieldName, string $expectedValue): void
    {
        // Use FormFiller to re-read and check value
        // We need the raw bytes for FormFiller, so grab them via generate
        // Instead, walk the AcroForm manually
        $trailer = $reader->getTrailer();
        $rootRef = $trailer->get('Root');
        $this->assertInstanceOf(PdfReference::class, $rootRef);

        $catalog = $reader->resolveReference($rootRef);
        $this->assertInstanceOf(PdfDictionary::class, $catalog);

        $acroFormVal = $catalog->get('AcroForm');
        $acroForm = $acroFormVal instanceof PdfReference
            ? $reader->resolveReference($acroFormVal)
            : $acroFormVal;
        $this->assertInstanceOf(PdfDictionary::class, $acroForm);

        $fields = $acroForm->get('Fields');
        $fieldsArray = $fields instanceof PdfReference
            ? $reader->resolveReference($fields)
            : $fields;
        $this->assertInstanceOf(PdfArray::class, $fieldsArray);

        $found = false;
        foreach ($fieldsArray->items as $fieldRef) {
            $fieldDict = $fieldRef instanceof PdfReference
                ? $reader->resolveReference($fieldRef)
                : $fieldRef;
            if (!$fieldDict instanceof PdfDictionary) {
                continue;
            }

            $t = $fieldDict->get('T');
            if ($t instanceof PdfString && $t->value === $fieldName) {
                $v = $fieldDict->get('V');
                if ($v instanceof PdfString) {
                    $this->assertSame($expectedValue, $v->value);
                } elseif ($v instanceof PdfName) {
                    $this->assertSame($expectedValue, $v->value);
                } else {
                    $this->fail("Field '$fieldName' has unexpected /V type: " . get_class($v));
                }
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, "Field '$fieldName' not found in the PDF");
    }
}
