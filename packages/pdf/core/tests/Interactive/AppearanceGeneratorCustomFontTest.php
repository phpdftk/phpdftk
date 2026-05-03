<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Tests\Interactive;

use Phpdftk\Pdf\Core\Graphics\XObject\FormXObject;
use Phpdftk\Pdf\Core\Interactive\Form\AppearanceGenerator;
use Phpdftk\Pdf\Core\Interactive\Form\FontContext;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfReference;
use PHPUnit\Framework\TestCase;

/**
 * Tests custom (composite) font rendering in AppearanceGenerator.
 *
 * Verifies that when a FontContext is supplied, appearance streams use
 * hex-encoded GID text operators and wire font references into Resources.
 */
class AppearanceGeneratorCustomFontTest extends TestCase
{
    private function makeRect(float $w = 200, float $h = 24): PdfArray
    {
        return new PdfArray([
            new PdfNumber(0), new PdfNumber(0),
            new PdfNumber($w), new PdfNumber($h),
        ]);
    }

    /**
     * Build a minimal FontContext mapping ASCII A-Z, a-z, 0-9, space, and common punctuation.
     */
    private function makeFontContext(int $objectNumber = 42): FontContext
    {
        $map = [];
        // Map each ASCII codepoint to a fake GID (codepoint + 100)
        for ($cp = 0x20; $cp <= 0x7E; $cp++) {
            $map[$cp] = $cp + 100;
        }
        return new FontContext(
            fontRef: new PdfReference($objectNumber),
            unicodeToGid: $map,
        );
    }

    // -----------------------------------------------------------------------
    // FontContext::textToHex
    // -----------------------------------------------------------------------

    public function testFontContextTextToHex(): void
    {
        $ctx = $this->makeFontContext();
        // 'A' = U+0041, GID = 0x41+100 = 165 = 0x00A5
        $hex = $ctx->textToHex('A');
        $this->assertSame('00A5', $hex);
    }

    public function testFontContextTextToHexMultipleChars(): void
    {
        $ctx = $this->makeFontContext();
        $hex = $ctx->textToHex('AB');
        // A=0x00A5, B=0x00A6
        $this->assertSame('00A500A6', $hex);
    }

    public function testFontContextTextToHexUnmappedCodepointUsesGidZero(): void
    {
        $ctx = $this->makeFontContext();
        // Unicode snowman U+2603 is not in our map → GID 0
        $hex = $ctx->textToHex("\u{2603}");
        $this->assertSame('0000', $hex);
    }

    public function testFontContextEmptyString(): void
    {
        $ctx = $this->makeFontContext();
        $this->assertSame('', $ctx->textToHex(''));
    }

    // -----------------------------------------------------------------------
    // textField with FontContext
    // -----------------------------------------------------------------------

    public function testTextFieldWithFontContextUsesHexEncoding(): void
    {
        $ctx = $this->makeFontContext();
        $xObj = AppearanceGenerator::textField(
            $this->makeRect(), 'F1', 12, 'Hi', fontContext: $ctx
        );

        $pdf = $xObj->toPdf();
        $this->assertStringContainsString('<' . $ctx->textToHex('Hi') . '> Tj', $pdf);
        $this->assertStringNotContainsString('(Hi)', $pdf);
    }

    public function testTextFieldWithFontContextWiresFontInResources(): void
    {
        $ctx = $this->makeFontContext(99);
        $xObj = AppearanceGenerator::textField(
            $this->makeRect(), 'F1', 12, 'Test', fontContext: $ctx
        );

        $pdf = $xObj->toPdf();
        // Resources should contain /Font << /F1 99 0 R >>
        $this->assertStringContainsString('/F1 99 0 R', $pdf);
    }

    public function testTextFieldWithoutFontContextStillUsesLiteralText(): void
    {
        $xObj = AppearanceGenerator::textField(
            $this->makeRect(), 'F1', 12, 'Hello'
        );

        $pdf = $xObj->toPdf();
        $this->assertStringContainsString('(Hello) Tj', $pdf);
    }

    public function testTextFieldWithFontContextEmptyValueOmitsText(): void
    {
        $ctx = $this->makeFontContext();
        $xObj = AppearanceGenerator::textField(
            $this->makeRect(), 'F1', 12, '', fontContext: $ctx
        );

        $pdf = $xObj->toPdf();
        $this->assertStringNotContainsString('BT', $pdf);
        $this->assertStringNotContainsString('Tj', $pdf);
    }

    public function testTextFieldWithFontContextPreservesBorder(): void
    {
        $ctx = $this->makeFontContext();
        $xObj = AppearanceGenerator::textField(
            $this->makeRect(), 'F1', 12, 'X', fontContext: $ctx
        );

        $pdf = $xObj->toPdf();
        $this->assertStringContainsString('re', $pdf);
        $this->assertMatchesRegularExpression('/\bS\b/', $pdf);
    }

    public function testTextFieldWithFontContextReturnsFormXObject(): void
    {
        $ctx = $this->makeFontContext();
        $xObj = AppearanceGenerator::textField(
            $this->makeRect(), 'F1', 12, 'Test', fontContext: $ctx
        );
        $this->assertInstanceOf(FormXObject::class, $xObj);
    }

    // -----------------------------------------------------------------------
    // textFieldMultiLine with FontContext
    // -----------------------------------------------------------------------

    public function testMultiLineWithFontContextUsesHexPerLine(): void
    {
        $ctx = $this->makeFontContext();
        $xObj = AppearanceGenerator::textFieldMultiLine(
            $this->makeRect(200, 100), 'F1', 12,
            "Line one\nLine two",
            fontContext: $ctx
        );

        $pdf = $xObj->toPdf();
        $this->assertStringContainsString('<' . $ctx->textToHex('Line one') . '> Tj', $pdf);
        $this->assertStringContainsString('<' . $ctx->textToHex('Line two') . '> Tj', $pdf);
        $this->assertStringContainsString('T*', $pdf);
    }

    public function testMultiLineWithFontContextWiresResources(): void
    {
        $ctx = $this->makeFontContext(55);
        $xObj = AppearanceGenerator::textFieldMultiLine(
            $this->makeRect(200, 100), 'F2', 12, 'Text',
            fontContext: $ctx
        );

        $pdf = $xObj->toPdf();
        $this->assertStringContainsString('/F2 55 0 R', $pdf);
    }

    // -----------------------------------------------------------------------
    // combTextField with FontContext
    // -----------------------------------------------------------------------

    public function testCombTextFieldWithFontContextUsesHexPerChar(): void
    {
        $ctx = $this->makeFontContext();
        $xObj = AppearanceGenerator::combTextField(
            $this->makeRect(200, 30), 'F1', 12, 'AB',
            maxLen: 5, fontContext: $ctx
        );

        $pdf = $xObj->toPdf();
        // Each char gets its own hex Tj
        $this->assertStringContainsString('<' . $ctx->textToHex('A') . '> Tj', $pdf);
        $this->assertStringContainsString('<' . $ctx->textToHex('B') . '> Tj', $pdf);
        // Should NOT use parenthesized strings
        $this->assertStringNotContainsString('(A)', $pdf);
        $this->assertStringNotContainsString('(B)', $pdf);
    }

    public function testCombTextFieldWithFontContextRespectsMaxLen(): void
    {
        $ctx = $this->makeFontContext();
        $xObj = AppearanceGenerator::combTextField(
            $this->makeRect(200, 30), 'F1', 12, 'ABCDEF',
            maxLen: 3, fontContext: $ctx
        );

        $pdf = $xObj->toPdf();
        $this->assertStringContainsString('<' . $ctx->textToHex('A') . '> Tj', $pdf);
        $this->assertStringContainsString('<' . $ctx->textToHex('C') . '> Tj', $pdf);
        $this->assertStringNotContainsString('<' . $ctx->textToHex('D') . '> Tj', $pdf);
    }

    // -----------------------------------------------------------------------
    // pushButton with FontContext
    // -----------------------------------------------------------------------

    public function testPushButtonWithFontContextUsesHex(): void
    {
        $ctx = $this->makeFontContext();
        $xObj = AppearanceGenerator::pushButton(
            $this->makeRect(100, 30), 'F1', 10, 'OK',
            fontContext: $ctx
        );

        $pdf = $xObj->toPdf();
        $this->assertStringContainsString('<' . $ctx->textToHex('OK') . '> Tj', $pdf);
        $this->assertStringNotContainsString('(OK)', $pdf);
    }

    // -----------------------------------------------------------------------
    // choiceField with FontContext
    // -----------------------------------------------------------------------

    public function testChoiceFieldWithFontContextUsesHex(): void
    {
        $ctx = $this->makeFontContext();
        $xObj = AppearanceGenerator::choiceField(
            $this->makeRect(), 'F1', 12, 'Option A',
            fontContext: $ctx
        );

        $pdf = $xObj->toPdf();
        $this->assertStringContainsString('<' . $ctx->textToHex('Option A') . '> Tj', $pdf);
    }

    // -----------------------------------------------------------------------
    // signatureField with FontContext
    // -----------------------------------------------------------------------

    public function testSignatureFieldWithFontContextUsesHex(): void
    {
        $ctx = $this->makeFontContext();
        $xObj = AppearanceGenerator::signatureField(
            $this->makeRect(200, 60), 'F1', 10,
            signer: 'Jane', reason: 'Test', fontContext: $ctx
        );

        $pdf = $xObj->toPdf();
        $this->assertStringContainsString(
            '<' . $ctx->textToHex('Digitally signed by Jane') . '> Tj', $pdf
        );
        $this->assertStringContainsString(
            '<' . $ctx->textToHex('Reason: Test') . '> Tj', $pdf
        );
    }

    public function testSignatureFieldWithFontContextWiresResources(): void
    {
        $ctx = $this->makeFontContext(77);
        $xObj = AppearanceGenerator::signatureField(
            $this->makeRect(200, 60), 'F3', 10, fontContext: $ctx
        );

        $pdf = $xObj->toPdf();
        $this->assertStringContainsString('/F3 77 0 R', $pdf);
    }

    // -----------------------------------------------------------------------
    // passwordField (delegates to textField, uses standard font only)
    // -----------------------------------------------------------------------

    public function testPasswordFieldStillWorksWithStandardFont(): void
    {
        $xObj = AppearanceGenerator::passwordField(
            $this->makeRect(), 'F1', 12, characterCount: 5
        );

        $pdf = $xObj->toPdf();
        $this->assertStringContainsString('(*****)', $pdf);
    }

    // -----------------------------------------------------------------------
    // Serialization round-trip
    // -----------------------------------------------------------------------

    public function testCustomFontAppearanceSerializesAsStream(): void
    {
        $ctx = $this->makeFontContext();
        $xObj = AppearanceGenerator::textField(
            $this->makeRect(), 'F1', 12, 'Test', fontContext: $ctx
        );

        $indirect = $xObj->toIndirectObject();
        $this->assertStringContainsString('stream', $indirect);
        $this->assertStringContainsString('endstream', $indirect);
        $this->assertStringContainsString('/Type /XObject', $indirect);
        $this->assertStringContainsString('/Subtype /Form', $indirect);
    }
}
