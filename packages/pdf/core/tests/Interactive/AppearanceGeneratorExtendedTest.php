<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Tests\Interactive;

use ApprLabs\Pdf\Core\Interactive\Form\AppearanceGenerator;
use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfNumber;
use PHPUnit\Framework\TestCase;

class AppearanceGeneratorExtendedTest extends TestCase
{
    private function makeRect(float $w = 200, float $h = 20): PdfArray
    {
        return new PdfArray([
            new PdfNumber(0), new PdfNumber(0),
            new PdfNumber($w), new PdfNumber($h),
        ]);
    }

    // -----------------------------------------------------------------------
    // Multi-line text field
    // -----------------------------------------------------------------------

    public function testMultiLineTextFieldRendersMultipleLines(): void
    {
        $xObj = AppearanceGenerator::textFieldMultiLine(
            $this->makeRect(200, 100),
            'F1', 12,
            "Line one\nLine two\nLine three"
        );

        $pdf = $xObj->toPdf();
        $this->assertStringContainsString('(Line one)', $pdf);
        $this->assertStringContainsString('(Line two)', $pdf);
        $this->assertStringContainsString('(Line three)', $pdf);
        $this->assertStringContainsString('T*', $pdf); // line advance operator
    }

    public function testMultiLineTextFieldWordWraps(): void
    {
        // With charWidth 0.5 and fontSize 12, avg char = 6pt
        // In a 100pt wide field (minus margins ~6pt), ~15 chars per line
        $longText = 'This is a long text that should wrap across multiple lines';

        $xObj = AppearanceGenerator::textFieldMultiLine(
            $this->makeRect(100, 80),
            'F1', 12,
            $longText
        );

        $pdf = $xObj->toPdf();
        // Should have T* operators for line breaks
        $this->assertStringContainsString('T*', $pdf);
        // Should have TL (text leading) set
        $this->assertStringContainsString('TL', $pdf);
    }

    public function testMultiLineTextFieldEmptyValue(): void
    {
        $xObj = AppearanceGenerator::textFieldMultiLine(
            $this->makeRect(200, 100),
            'F1', 12,
            ''
        );

        $pdf = $xObj->toPdf();
        $this->assertStringNotContainsString('BT', $pdf);
    }

    // -----------------------------------------------------------------------
    // Password field
    // -----------------------------------------------------------------------

    public function testPasswordFieldRendersMaskedCharacters(): void
    {
        $xObj = AppearanceGenerator::passwordField(
            $this->makeRect(),
            'F1', 12,
            characterCount: 8
        );

        $pdf = $xObj->toPdf();
        // Should render 8 asterisks, not the actual password
        $this->assertStringContainsString('(********)', $pdf);
    }

    public function testPasswordFieldZeroCharsShowsNothing(): void
    {
        $xObj = AppearanceGenerator::passwordField(
            $this->makeRect(),
            'F1', 12,
            characterCount: 0
        );

        $pdf = $xObj->toPdf();
        // No text operators
        $this->assertStringNotContainsString('Tj', $pdf);
    }

    // -----------------------------------------------------------------------
    // Comb text field
    // -----------------------------------------------------------------------

    public function testCombTextFieldDrawsCellDividers(): void
    {
        $xObj = AppearanceGenerator::combTextField(
            $this->makeRect(200, 30),
            'F1', 12,
            'ABCDE',
            maxLen: 10,
        );

        $pdf = $xObj->toPdf();
        // Should have individual character renders
        $this->assertStringContainsString('(A)', $pdf);
        $this->assertStringContainsString('(B)', $pdf);
        $this->assertStringContainsString('(E)', $pdf);
        // Should have vertical divider lines (9 for maxLen=10)
        $this->assertGreaterThan(5, substr_count($pdf, ' l S'));
    }

    public function testCombTextFieldRespectsMaxLen(): void
    {
        $xObj = AppearanceGenerator::combTextField(
            $this->makeRect(200, 30),
            'F1', 12,
            'ABCDEFGHIJKLMNOP', // 16 chars
            maxLen: 5,
        );

        $pdf = $xObj->toPdf();
        // Only first 5 characters should render
        $this->assertStringContainsString('(A)', $pdf);
        $this->assertStringContainsString('(E)', $pdf);
        $this->assertStringNotContainsString('(F)', $pdf);
    }

    public function testCombTextFieldEmptyValue(): void
    {
        $xObj = AppearanceGenerator::combTextField(
            $this->makeRect(200, 30),
            'F1', 12,
            '',
            maxLen: 5,
        );

        $pdf = $xObj->toPdf();
        // Should have dividers but no text
        $this->assertStringContainsString(' l S', $pdf); // dividers
        $this->assertStringNotContainsString('Tj', $pdf); // no text
    }

    // -----------------------------------------------------------------------
    // Signature field
    // -----------------------------------------------------------------------

    public function testSignatureFieldRendersSignerInfo(): void
    {
        $xObj = AppearanceGenerator::signatureField(
            $this->makeRect(200, 60),
            'F1', 10,
            signer: 'John Doe',
            reason: 'Approval',
            date: '2024-01-15',
        );

        $pdf = $xObj->toPdf();
        $this->assertStringContainsString('Digitally signed by John Doe', $pdf);
        $this->assertStringContainsString('Reason: Approval', $pdf);
        $this->assertStringContainsString('Date: 2024-01-15', $pdf);
    }

    public function testSignatureFieldDefaultText(): void
    {
        $xObj = AppearanceGenerator::signatureField(
            $this->makeRect(200, 60),
            'F1', 10,
        );

        $pdf = $xObj->toPdf();
        $this->assertStringContainsString('Digital Signature', $pdf);
    }

    public function testSignatureFieldHasBlueishBackground(): void
    {
        $xObj = AppearanceGenerator::signatureField(
            $this->makeRect(200, 60),
            'F1', 10,
        );

        $pdf = $xObj->toPdf();
        // Light blue fill
        $this->assertStringContainsString('0.93 0.95 1.0 rg', $pdf);
    }
}
