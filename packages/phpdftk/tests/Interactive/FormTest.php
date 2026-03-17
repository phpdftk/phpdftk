<?php

declare(strict_types=1);

namespace Phpdftk\Tests\Interactive;

use PHPUnit\Framework\TestCase;
use Phpdftk\Interactive\Form\AcroForm;
use Phpdftk\Interactive\Form\ButtonField;
use Phpdftk\Interactive\Form\ChoiceField;
use Phpdftk\Interactive\Form\SignatureField;
use Phpdftk\Interactive\Form\TextField;
use Phpdftk\Core\PdfArray;
use Phpdftk\Core\PdfName;
use Phpdftk\Core\PdfNumber;
use Phpdftk\Core\PdfReference;
use Phpdftk\Core\PdfString;

class FormTest extends TestCase
{
    // -----------------------------------------------------------------------
    // AcroForm
    // -----------------------------------------------------------------------

    public function testAcroFormEmptyFields(): void
    {
        $form = new AcroForm();
        $form->objectNumber = 1;
        $pdf = $form->toPdf();
        self::assertStringContainsString('/Fields', $pdf);
    }

    public function testAcroFormWithFields(): void
    {
        $form = new AcroForm();
        $form->objectNumber = 1;
        $form->fields = [new PdfReference(5), new PdfReference(6)];
        $pdf = $form->toPdf();
        self::assertStringContainsString('/Fields', $pdf);
        self::assertStringContainsString('5 0 R', $pdf);
        self::assertStringContainsString('6 0 R', $pdf);
    }

    public function testAcroFormNeedAppearances(): void
    {
        $form = new AcroForm();
        $form->objectNumber = 1;
        $form->needAppearances = true;
        $pdf = $form->toPdf();
        self::assertStringContainsString('/NeedAppearances true', $pdf);
    }

    public function testAcroFormSigFlags(): void
    {
        $form = new AcroForm();
        $form->objectNumber = 1;
        $form->sigFlags = 3;
        $pdf = $form->toPdf();
        self::assertStringContainsString('/SigFlags 3', $pdf);
    }

    public function testAcroFormDefaultAppearance(): void
    {
        $form = new AcroForm();
        $form->objectNumber = 1;
        $form->da = new PdfString('/Helvetica 12 Tf 0 g');
        $pdf = $form->toPdf();
        self::assertStringContainsString('/DA', $pdf);
    }

    public function testAcroFormJustification(): void
    {
        $form = new AcroForm();
        $form->objectNumber = 1;
        $form->q = 1; // center
        $pdf = $form->toPdf();
        self::assertStringContainsString('/Q 1', $pdf);
    }

    public function testAcroFormToIndirectObject(): void
    {
        $form = new AcroForm();
        $form->objectNumber = 7;
        $form->generationNumber = 0;
        $indirect = $form->toIndirectObject();
        self::assertStringContainsString('7 0 obj', $indirect);
        self::assertStringContainsString('endobj', $indirect);
    }

    // -----------------------------------------------------------------------
    // TextField
    // -----------------------------------------------------------------------

    public function testTextFieldFT(): void
    {
        $field = new TextField();
        $field->objectNumber = 1;
        $pdf = $field->toPdf();
        self::assertStringContainsString('/FT /Tx', $pdf);
    }

    public function testTextFieldWithName(): void
    {
        $field = new TextField();
        $field->objectNumber = 1;
        $field->t = new PdfString('FirstName');
        $pdf = $field->toPdf();
        self::assertStringContainsString('/T', $pdf);
    }

    public function testTextFieldMaxLen(): void
    {
        $field = new TextField();
        $field->objectNumber = 1;
        $field->maxLen = 100;
        $pdf = $field->toPdf();
        self::assertStringContainsString('/MaxLen 100', $pdf);
    }

    public function testTextFieldJustification(): void
    {
        $field = new TextField();
        $field->objectNumber = 1;
        $field->q = 2; // right
        $pdf = $field->toPdf();
        self::assertStringContainsString('/Q 2', $pdf);
    }

    public function testTextFieldWithValue(): void
    {
        $field = new TextField();
        $field->objectNumber = 1;
        $field->v = new PdfString('Hello');
        $pdf = $field->toPdf();
        self::assertStringContainsString('/V', $pdf);
    }

    public function testTextFieldWithDefaultValue(): void
    {
        $field = new TextField();
        $field->objectNumber = 1;
        $field->dv = new PdfString('Default text');
        $pdf = $field->toPdf();
        self::assertStringContainsString('/DV', $pdf);
    }

    public function testTextFieldFlags(): void
    {
        $field = new TextField();
        $field->objectNumber = 1;
        $field->ff = 4096; // Multiline
        $pdf = $field->toPdf();
        self::assertStringContainsString('/Ff 4096', $pdf);
    }

    public function testTextFieldWithParent(): void
    {
        $field = new TextField();
        $field->objectNumber = 1;
        $field->parent = new PdfReference(3);
        $pdf = $field->toPdf();
        self::assertStringContainsString('/Parent 3 0 R', $pdf);
    }

    public function testTextFieldUserName(): void
    {
        $field = new TextField();
        $field->objectNumber = 1;
        $field->tu = new PdfString('First Name');
        $pdf = $field->toPdf();
        self::assertStringContainsString('/TU', $pdf);
    }

    // -----------------------------------------------------------------------
    // ButtonField
    // -----------------------------------------------------------------------

    public function testButtonFieldFT(): void
    {
        $field = new ButtonField();
        $field->objectNumber = 1;
        $pdf = $field->toPdf();
        self::assertStringContainsString('/FT /Btn', $pdf);
    }

    public function testButtonFieldHighlightMode(): void
    {
        $field = new ButtonField();
        $field->objectNumber = 1;
        $field->h = new PdfName('I');
        $pdf = $field->toPdf();
        self::assertStringContainsString('/H /I', $pdf);
    }

    public function testButtonFieldOptions(): void
    {
        $field = new ButtonField();
        $field->objectNumber = 1;
        $field->opt = new PdfArray([new PdfString('Yes'), new PdfString('No')]);
        $pdf = $field->toPdf();
        self::assertStringContainsString('/Opt', $pdf);
    }

    public function testButtonFieldWithName(): void
    {
        $field = new ButtonField();
        $field->objectNumber = 1;
        $field->t = new PdfString('Checkbox1');
        $pdf = $field->toPdf();
        self::assertStringContainsString('/T', $pdf);
    }

    // -----------------------------------------------------------------------
    // ChoiceField
    // -----------------------------------------------------------------------

    public function testChoiceFieldFT(): void
    {
        $field = new ChoiceField();
        $field->objectNumber = 1;
        $pdf = $field->toPdf();
        self::assertStringContainsString('/FT /Ch', $pdf);
    }

    public function testChoiceFieldOptions(): void
    {
        $field = new ChoiceField();
        $field->objectNumber = 1;
        $field->opt = new PdfArray([
            new PdfString('Option 1'),
            new PdfString('Option 2'),
            new PdfString('Option 3'),
        ]);
        $pdf = $field->toPdf();
        self::assertStringContainsString('/Opt', $pdf);
    }

    public function testChoiceFieldTopIndex(): void
    {
        $field = new ChoiceField();
        $field->objectNumber = 1;
        $field->ti = 0;
        $pdf = $field->toPdf();
        self::assertStringContainsString('/TI 0', $pdf);
    }

    public function testChoiceFieldSelectedIndices(): void
    {
        $field = new ChoiceField();
        $field->objectNumber = 1;
        $field->i = new PdfArray([new PdfNumber(0)]);
        $pdf = $field->toPdf();
        self::assertStringContainsString('/I', $pdf);
    }

    // -----------------------------------------------------------------------
    // SignatureField
    // -----------------------------------------------------------------------

    public function testSignatureFieldFT(): void
    {
        $field = new SignatureField();
        $field->objectNumber = 1;
        $pdf = $field->toPdf();
        self::assertStringContainsString('/FT /Sig', $pdf);
    }

    public function testSignatureFieldSigFlags(): void
    {
        $field = new SignatureField();
        $field->objectNumber = 1;
        $field->sigFlags = 1;
        $pdf = $field->toPdf();
        self::assertStringContainsString('/SigFlags 1', $pdf);
    }

    public function testSignatureFieldWithName(): void
    {
        $field = new SignatureField();
        $field->objectNumber = 1;
        $field->t = new PdfString('Signature1');
        $pdf = $field->toPdf();
        self::assertStringContainsString('/T', $pdf);
    }

    public function testSignatureFieldToIndirectObject(): void
    {
        $field = new SignatureField();
        $field->objectNumber = 8;
        $field->generationNumber = 0;
        $indirect = $field->toIndirectObject();
        self::assertStringContainsString('8 0 obj', $indirect);
        self::assertStringContainsString('endobj', $indirect);
    }

    // -----------------------------------------------------------------------
    // Field base class: kids
    // -----------------------------------------------------------------------

    public function testFieldWithKids(): void
    {
        $field = new TextField();
        $field->objectNumber = 1;
        $field->kids = [new PdfReference(10), new PdfReference(11)];
        $pdf = $field->toPdf();
        self::assertStringContainsString('/Kids', $pdf);
        self::assertStringContainsString('10 0 R', $pdf);
    }
}
