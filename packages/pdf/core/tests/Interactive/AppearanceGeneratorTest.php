<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Tests\Interactive;

use Phpdftk\Pdf\Core\Interactive\Form\AppearanceGenerator;
use Phpdftk\Pdf\Core\Graphics\XObject\FormXObject;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfReference;
use PHPUnit\Framework\TestCase;

class AppearanceGeneratorTest extends TestCase
{
    private function makeRect(float $x1 = 0, float $y1 = 0, float $x2 = 200, float $y2 = 24): PdfArray
    {
        return new PdfArray([
            new PdfNumber($x1), new PdfNumber($y1),
            new PdfNumber($x2), new PdfNumber($y2),
        ]);
    }

    // -----------------------------------------------------------------------
    // Text field
    // -----------------------------------------------------------------------

    public function testTextFieldGeneratesFormXObject(): void
    {
        $xObj = AppearanceGenerator::textField($this->makeRect(), 'F1', 12);
        $this->assertInstanceOf(FormXObject::class, $xObj);
    }

    public function testTextFieldWithValueContainsText(): void
    {
        $xObj = AppearanceGenerator::textField($this->makeRect(), 'F1', 12, 'Hello');
        $pdf = $xObj->toPdf();
        $this->assertStringContainsString('BT', $pdf);
        $this->assertStringContainsString('/F1 12', $pdf);
        $this->assertStringContainsString('(Hello)', $pdf);
    }

    public function testTextFieldEmptyValueOmitsText(): void
    {
        $xObj = AppearanceGenerator::textField($this->makeRect(), 'F1', 12, '');
        $pdf = $xObj->toPdf();
        $this->assertStringNotContainsString('BT', $pdf);
    }

    public function testTextFieldHasBorder(): void
    {
        $xObj = AppearanceGenerator::textField($this->makeRect(), 'F1', 12);
        $pdf = $xObj->toPdf();
        $this->assertStringContainsString('re', $pdf);
        $this->assertMatchesRegularExpression('/\bS\b/', $pdf);
    }

    public function testTextFieldEscapesSpecialChars(): void
    {
        $xObj = AppearanceGenerator::textField($this->makeRect(), 'F1', 12, 'test(parens)');
        $pdf = $xObj->toPdf();
        $this->assertStringContainsString('test\\(parens\\)', $pdf);
    }

    // -----------------------------------------------------------------------
    // Checkbox
    // -----------------------------------------------------------------------

    public function testCheckboxReturnsTwoStates(): void
    {
        $states = AppearanceGenerator::checkbox($this->makeRect(0, 0, 18, 18));
        $this->assertArrayHasKey('on', $states);
        $this->assertArrayHasKey('off', $states);
        $this->assertInstanceOf(FormXObject::class, $states['on']);
        $this->assertInstanceOf(FormXObject::class, $states['off']);
    }

    public function testCheckboxOnStateHasCheckMark(): void
    {
        $states = AppearanceGenerator::checkbox($this->makeRect(0, 0, 18, 18));
        $onPdf = $states['on']->toPdf();
        // Check mark uses line operators (m, l, S)
        $this->assertStringContainsString(' m', $onPdf);
        $this->assertStringContainsString(' l', $onPdf);
    }

    public function testCheckboxOffStateHasNoCross(): void
    {
        $states = AppearanceGenerator::checkbox($this->makeRect(0, 0, 18, 18));
        $offPdf = $states['off']->toPdf();
        $onPdf = $states['on']->toPdf();
        // Off state should be shorter than on state (no check mark lines)
        $this->assertLessThan(strlen($onPdf), strlen($offPdf));
    }

    // -----------------------------------------------------------------------
    // Radio button
    // -----------------------------------------------------------------------

    public function testRadioButtonReturnsTwoStates(): void
    {
        $states = AppearanceGenerator::radioButton($this->makeRect(0, 0, 18, 18));
        $this->assertArrayHasKey('on', $states);
        $this->assertArrayHasKey('off', $states);
    }

    public function testRadioButtonUsesCircle(): void
    {
        $states = AppearanceGenerator::radioButton($this->makeRect(0, 0, 18, 18));
        $pdf = $states['off']->toPdf();
        // Circle approximated with Bézier curves (c operator)
        $this->assertStringContainsString(' c', $pdf);
    }

    // -----------------------------------------------------------------------
    // Push button
    // -----------------------------------------------------------------------

    public function testPushButtonWithLabel(): void
    {
        $xObj = AppearanceGenerator::pushButton($this->makeRect(0, 0, 100, 30), 'F1', 10, 'Submit');
        $pdf = $xObj->toPdf();
        $this->assertStringContainsString('(Submit)', $pdf);
    }

    // -----------------------------------------------------------------------
    // Choice field
    // -----------------------------------------------------------------------

    public function testChoiceFieldAppearance(): void
    {
        $xObj = AppearanceGenerator::choiceField($this->makeRect(), 'F1', 12, 'Option 1');
        $pdf = $xObj->toPdf();
        $this->assertStringContainsString('(Option 1)', $pdf);
    }

    // -----------------------------------------------------------------------
    // AppearanceDict builders
    // -----------------------------------------------------------------------

    public function testBuildAppearanceDict(): void
    {
        $ref = new PdfReference(10);
        $ap = AppearanceGenerator::buildAppearanceDict($ref);
        $pdf = $ap->toPdf();
        $this->assertStringContainsString('/N 10 0 R', $pdf);
    }

    public function testBuildStateAppearanceDict(): void
    {
        $onRef = new PdfReference(10);
        $offRef = new PdfReference(11);
        $ap = AppearanceGenerator::buildStateAppearanceDict($onRef, $offRef, 'Yes');
        $pdf = $ap->toPdf();
        $this->assertStringContainsString('/Yes', $pdf);
        $this->assertStringContainsString('/Off', $pdf);
    }

    // -----------------------------------------------------------------------
    // Integration: generate + serialize
    // -----------------------------------------------------------------------

    public function testGeneratedAppearanceSerializesAsStream(): void
    {
        $xObj = AppearanceGenerator::textField($this->makeRect(), 'F1', 12, 'Test');
        $pdf = $xObj->toIndirectObject();
        $this->assertStringContainsString('stream', $pdf);
        $this->assertStringContainsString('endstream', $pdf);
        $this->assertStringContainsString('/Type /XObject', $pdf);
        $this->assertStringContainsString('/Subtype /Form', $pdf);
    }
}
