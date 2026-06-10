<?php

declare(strict_types=1);

namespace Phpdftk\MathmlToPdf\Tests;

use Phpdftk\Mathml\Parser as MathmlParser;
use Phpdftk\MathmlToPdf\MathmlRenderer;
use Phpdftk\Pdf\Writer\PdfWriter;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for the maction / merror / semantics
 * passthrough painters.
 *
 * For maction, we verify that the `selection`-th child wins:
 * <maction selection="2"><mi>X</mi><mi>Y</mi></maction> should
 * emit a 'Y' literal and not an 'X'.
 *
 * For merror, we verify children render inline.
 *
 * For semantics, we verify the first child renders and the
 * trailing annotation does not.
 */
final class ActionErrorSemanticsRenderTest extends TestCase
{
    public function testMactionDefaultRendersFirstChild(): void
    {
        $bytes = $this->render(
            '<maction><mi>X</mi><mi>Y</mi></maction>',
        );
        self::assertStringStartsWith('%PDF-', $bytes);
        self::assertMatchesRegularExpression('/\(X\)\s+Tj/', $bytes);
        self::assertDoesNotMatchRegularExpression('/\(Y\)\s+Tj/', $bytes);
    }

    public function testMactionSelectionTwoRendersSecondChild(): void
    {
        $bytes = $this->render(
            '<maction selection="2"><mi>X</mi><mi>Y</mi></maction>',
        );
        self::assertMatchesRegularExpression('/\(Y\)\s+Tj/', $bytes);
        self::assertDoesNotMatchRegularExpression('/\(X\)\s+Tj/', $bytes);
    }

    public function testMactionOutOfRangeFallsBackToFirstChild(): void
    {
        $bytes = $this->render(
            '<maction selection="99"><mi>X</mi><mi>Y</mi></maction>',
        );
        self::assertMatchesRegularExpression('/\(X\)\s+Tj/', $bytes);
        self::assertDoesNotMatchRegularExpression('/\(Y\)\s+Tj/', $bytes);
    }

    public function testEmptyMactionEmitsNothing(): void
    {
        $bytes = $this->render('<maction></maction>');
        self::assertStringStartsWith('%PDF-', $bytes);
    }

    public function testMerrorRendersChildren(): void
    {
        $bytes = $this->render('<merror><mtext>broken</mtext></merror>');
        self::assertMatchesRegularExpression('/\(broken\)\s+Tj/', $bytes);
    }

    public function testSemanticsRendersFirstChildOnly(): void
    {
        // <semantics> renders <mi>X</mi> only; the trailing
        // <annotation> carries TeX source and must NOT appear in
        // the content stream as a Tj.
        $bytes = $this->render(
            '<semantics>'
            . '<mi>X</mi>'
            . '<annotation encoding="TeX">x_source</annotation>'
            . '</semantics>',
        );
        self::assertMatchesRegularExpression('/\(X\)\s+Tj/', $bytes);
        self::assertDoesNotMatchRegularExpression(
            '/\(x_source\)\s+Tj/',
            $bytes,
        );
    }

    public function testSemanticsEmptyEmitsNothing(): void
    {
        $bytes = $this->render('<semantics></semantics>');
        self::assertStringStartsWith('%PDF-', $bytes);
    }

    private function render(string $innerXml): string
    {
        $xml = '<math xmlns="http://www.w3.org/1998/Math/MathML">'
            . $innerXml . '</math>';
        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage();
        $renderer = new MathmlRenderer($page, $writer);
        $doc = (new MathmlParser())->parse($xml);
        $renderer->draw($doc, x: 72.0, y: 600.0, width: 200.0, height: 30.0);
        return $writer->toBytes();
    }
}
