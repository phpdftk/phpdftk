<?php

declare(strict_types=1);

namespace Phpdftk\MathmlToPdf\Tests;

use Phpdftk\Mathml\Parser as MathmlParser;
use Phpdftk\MathmlToPdf\MathmlRenderer;
use Phpdftk\Pdf\Writer\PdfWriter;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end tests that verify token elements with
 * `mathvariant` set produce PDFs whose content stream carries
 * the transformed Mathematical Alphanumeric Symbols codepoints,
 * not the raw ASCII source.
 *
 * The standard 14 fonts can't render U+1D400+ - the encoder
 * emits the substitute char for codepoints outside WinAnsi.
 * What we can verify is that the painter *does* apply the
 * transform (the source `x` no longer appears verbatim in the
 * Tj string) and the PDF is valid.
 */
final class MathvariantIntegrationTest extends TestCase
{
    public function testBoldMiTransformsContent(): void
    {
        $bytes = $this->render('<mi mathvariant="bold">x</mi>');
        self::assertStringStartsWith('%PDF-', $bytes);
        // After transform, the source 'x' should NOT appear as a
        // bare literal in a Tj. Standard fonts substitute U+1D431
        // with '?' since it's outside WinAnsi.
        self::assertDoesNotMatchRegularExpression(
            '/\(x\)\s+Tj/',
            $bytes,
        );
    }

    public function testNormalMiKeepsSourceLiteral(): void
    {
        $bytes = $this->render('<mi mathvariant="normal">x</mi>');
        self::assertStringStartsWith('%PDF-', $bytes);
        // 'normal' is identity - 'x' must appear verbatim.
        self::assertMatchesRegularExpression('/\(x\)\s+Tj/', $bytes);
    }

    public function testMissingMathvariantKeepsAutoItalicBehavior(): void
    {
        // No mathvariant: paintMi's auto-italic font swap fires for
        // single-letter content but the source codepoint is
        // unchanged, so 'x' still shows in the Tj literal.
        $bytes = $this->render('<mi>x</mi>');
        self::assertMatchesRegularExpression('/\(x\)\s+Tj/', $bytes);
    }

    public function testBoldMnTransformsDigits(): void
    {
        $bytes = $this->render('<mn mathvariant="bold">5</mn>');
        self::assertStringStartsWith('%PDF-', $bytes);
        // After transform, '5' becomes U+1D7D3 which is outside
        // WinAnsi - the raw '5' should no longer appear in a Tj.
        self::assertDoesNotMatchRegularExpression(
            '/\(5\)\s+Tj/',
            $bytes,
        );
    }

    public function testMtextWithMathvariantSurvives(): void
    {
        $bytes = $this->render(
            '<mtext mathvariant="double-struck">R</mtext>',
        );
        self::assertStringStartsWith('%PDF-', $bytes);
    }

    public function testUnknownMathvariantIsIdentity(): void
    {
        // Unknown variant names should be a no-op so we don't blow
        // up on unexpected input.
        $bytes = $this->render('<mi mathvariant="banana">x</mi>');
        self::assertMatchesRegularExpression('/\(x\)\s+Tj/', $bytes);
    }

    public function testMixedAsciiAndPunctuationInMo(): void
    {
        // Operators usually carry non-letter content; the transform
        // should be a no-op for those even when mathvariant is set.
        $bytes = $this->render('<mo mathvariant="bold">+</mo>');
        self::assertMatchesRegularExpression('/\(\+\)\s+Tj/', $bytes);
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
